<?php
// This file is part of Moodle - http://moodle.org/
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Import Excel file into Folder.
 *
 * @package    local_course_folderfileimport
 * @copyright  2020 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
// require_once(__DIR__.'/locallib.php');
require_once(__DIR__.'/importupload_form.php');
require_once(__DIR__.'/lib.php');
// require_once($CFG->dirroot . '/mod/glossary/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
// require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/mod/folder/lib.php');


// The courseid we are importing to.
$courseid = required_param('id', PARAM_INT);
$action = optional_param('action', 'import', PARAM_TEXT);  // Import.

// Load the course and context.
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$context = context_course::instance($courseid); //课程的上下文

// Security checks.
// 安全检查
require_course_login($course, true);

// Check import/export capabilities.
// 检查文件夹添加权限
require_capability('mod/folder:addinstance', $context);
require_capability('mod/folder:managefiles', $context);


// Set up page in case an import has been requested.
$PAGE->set_url('/local/course_folderfileimport/import.php', array('id' => $courseid, 'action' => $action));
$PAGE->set_title($course->fullname);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading($course->fullname);

// Set up the Word Excel upload form.
// 数据导入页面
$mform = new local_course_folderfileimport_form(null, array('id' => $courseid, 'action' => $action));
if ($mform->is_cancelled()) {
    // Form cancelled, go back.
    redirect($CFG->wwwroot . "/course/view.php?id=$courseid");
}

// Display or process the Excel file upload form.
// 显示和处理数据导入界面
$data = $mform->get_data();
if (!$data) { // Display the form.
    $mform->display();
}else {
    // Import: save the uploaded Excel file to the file system for processing.
    $fs = get_file_storage();
    $draftid = file_get_submitted_draft_itemid('importfile');
    if (!$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false)) {
        redirect($PAGE->url);
    }
    $file = reset($files);

    // 读取csv数据到数组
    setlocale(LC_ALL,array('zh_CN.gbk','zh_CN.gb2312','zh_CN.gb18030'));
    $fh = $file->get_content_file_handle(); 

    $line_num = 0;
    $csv_header = array();
    $csv_array = array();
    while (($line = fgetcsv($fh, 0, ",")) !== false) {
        $line_num++;
        if($line_num == 1){
            $csv_header = $line;/* 第一行抬头不算*/ 
            continue;
        }
        $csv_data = array();
        for($j = 0; $j < count($csv_header); $j++){
            $csv_data[$csv_header[$j]] = $line[$j]; 
        }
        if($csv_data){
            $csv_array[] = $csv_data;
            // array_push($csv_array,$csv_data);
        }
    }
    fclose($fh);

    // 处理数组
    $upload_array = array('course_name'=>'', 'chapter'=>array());
    $chapter_num = 0;
    foreach ($csv_array as $line_array){
        if (!$upload_array['course_name']){
            $upload_array['course_name'] = iconv("GB2312","UTF-8",$line_array['course_id']);
        }
        $content_type = iconv("GB2312","UTF-8",$line_array['content_type']);
        if ($content_type == '章节'){
            $upload_array['chapter'][]= array('name'=>iconv("GB2312","UTF-8",$line_array['name']), 'video'=>array());
            $chapter_num++;
        }
        if ($content_type == '课程视频'){
            $upload_array['chapter'][$chapter_num-1]['video'][]= array('name'=>iconv("GB2312","UTF-8",$line_array['name']), 
                    'video_url'=>iconv("GB2312","UTF-8",$line_array['video_url']));
        }   
    }

    // 输出处理过程
    echo $OUTPUT->box_start('glossarydisplay generalbox');
    echo '<table class="glossaryimportexport">';

    // 导入文件所在的容器
    $repos_pluginname = get_config('local_course_folderfileimport', 'repos_pluginname'); //'ossbucket'; //使用的容器插件        
    $repos_instance = get_config('local_course_folderfileimport', 'repos_instance'); //'AliyunOSSTemp';
    $repos = repository::get_instances(array('type' => $repos_pluginname)); // 容器实例
    foreach($repos as $repo){
        if($repo->name == $repos_instance){
            $importrepository = $repo;

            echo '<tr><td>'.'导入所在容器：'.$importrepository->name.'</td></tr>';

            // 创建主题
            $section = course_create_section($course, 0);
            course_update_section($course, $section, array('name'=>$section->section . ' ' . $upload_array['course_name']));    
            
            echo '<tr><td>'.'主题：'.$upload_array['course_name'].'</td></tr>';

            foreach($upload_array['chapter'] as $chaper_array){
                // 按章节创建文件夹
                // 创建课程模块文件夹
                list($module, $context, $cw, $cm, $infodata) = prepare_new_moduleinfo_data($course, 'folder', $section->section);
                $infodata->coursemodule = $infodata->id = add_course_module($infodata);

                // 创建文件夹
                $importname_trim = get_config('local_course_folderfileimport', 'importname_trim');
                $folderdata = new stdClass();
                $folderdata->course = $courseid;
                $folderdata->name = substr($chaper_array['name'],$importname_trim);
                $folderdata->intro = '';
                $folderdata->introformat = FORMAT_HTML;
                $folderdata->showdownloadfolder = 0;
                $folderdata->forcedownload = 0;
                $folderdata->coursemodule = $infodata->coursemodule;    // folder对应course_modules
                $folderdata->files = null; 
                $folderdata->id = folder_add_instance($folderdata, null);

                echo '<tr><td style="padding-left:30px;">'.$folderdata->name.'</td></tr>';

                // 添加文件到文件夹
                $context = context_module::instance($infodata->coursemodule); //folder对应course_modules的mdl_context 

                foreach($chaper_array['video'] as $video_array){
                    $extension = pathinfo( $video_array['video_url'], PATHINFO_EXTENSION);
                    $filename = substr($video_array['name'],$importname_trim) . '.' . $extension;
                    $fs->create_file_from_reference([
                        'contextid' => $context->id,
                        'component' => 'mod_folder',
                        'filearea'  => 'content',
                        'itemid'    => 0,
                        'filepath'  => '/',
                        'filename'  => $filename,
                        'userid'    => $USER->id,
                        'author'    => fullname($USER),
                    ], $importrepository->id, $video_array['video_url']);

                    echo '<tr><td style="padding-left:60px;">'.$filename.'</td></tr>';
                }

                // 将文件夹添加到section
                course_add_cm_to_section($course, $infodata->coursemodule, $section->section);

            }
            break;

        }
    }

    echo '</table><hr />';

    echo $OUTPUT->continue_button(new moodle_url('/course/view.php', array('id' => $courseid)));
    echo $OUTPUT->box_end();


}


// Finish the page.
// 完成页面
echo $OUTPUT->footer();