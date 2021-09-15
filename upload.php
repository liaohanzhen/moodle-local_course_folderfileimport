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
 * Import Word file into glossary.
 *
 * @package    local_course_folderfileimport
 * @copyright  2020 Eoin Campbell
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
// require_once(__DIR__.'/locallib.php');
require_once(__DIR__.'/importupload_form.php');
require_once('lib.php');
// require_once($CFG->dirroot . '/mod/glossary/locallib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
// require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/local/aliyunoss/sdk/autoload.php');
use OSS\OssClient;
use OSS\Core\OssException;

// The courseid we are upload to.
$id = required_param('id', PARAM_INT); // Course Module ID (this folder).
$exportformat = optional_param('imageformat', 'embedded', PARAM_TEXT);  // Image encoding format for export.

// Security checks.
list ($course, $cm) = get_course_and_cm_from_cmid($id, 'folder');
$folder = $DB->get_record('folder', array('id' => $cm->instance), '*', MUST_EXIST);
require_course_login($course, true, $cm);

// Check import/export capabilities.
$context = context_module::instance($cm->id);
require_capability('mod/folder:addinstance', $context);
require_capability('mod/folder:managefiles', $context);

// Set up page in case an import has been requested.
$PAGE->set_url('/local/course_folderfileimport/upload.php', array('id' => $id));
$PAGE->set_title($folder->name);
$PAGE->set_heading($course->fullname);
// 阿里云Client
$_ossclient;

echo $OUTPUT->header();
echo $OUTPUT->heading($folder->name);

// Set up the file upload form.
$mform = new local_folder_fileupload_form(null, array('id' => $id));
if ($mform->is_cancelled()) {
    // Form cancelled, go back.
    redirect($CFG->wwwroot . "/mod/folder/view.php?id=$cm->id");
}

$uploadrepository = repository::get_repository_by_id(
    get_config('local_course_folderfileupload', 'upload_repository'), 
    $context);
$oss = create_oss($uploadrepository);

// Display or process the Word file upload form.
$data = $mform->get_data();
if (!$data) { // Display the form.
    $mform->display();
} else {
    // Import: upload the file to AliyunOSS for processing.
    $fs = get_file_storage();
    $draftid = file_get_submitted_draft_itemid('uploadfiles');
    if (!$files = $fs->get_area_files(context_user::instance($USER->id)->id, 'user', 'draft', $draftid, 'id DESC', false)) {
        redirect($PAGE->url);
    }

    printf("上传文件到AliyunOSS:\n<br/>");
    foreach ($files as $file) {
        $filename = $file->get_filename();
        $prefix = get_config('local_course_folderfileupload', 'upload_prefix');  // 保存到OSS路径前缀
        $contenthash = $file->get_contenthash();
        $path = sprintf("%s/%s/%s/",substr($contenthash,0,2),substr($contenthash,2,2),$contenthash); //根据文件内容的hash字符串作为路径
        $object =  $prefix . $path . $filename;
        $content = $file->get_content();
        $options = array();

        printf($filename.'>>'.$prefix . $path.',');
        // 上传文件
        try {
            $oss->putObject($uploadrepository->get_option('bucket_name'), $object, $content, $options);
        } catch (OssException $e) {
            printf(__FUNCTION__ . ": FAILED:");
            printf($e->getMessage() . "\n<br/>");
            return;
        }
        // 建立文件
        $fs->create_file_from_reference([
            'contextid' => $context->id,
            'component' => 'mod_folder',
            'filearea'  => 'content',
            'itemid'    => 0,
            'filepath'  => '/',
            'filename'  => $filename,
            'userid'    => $USER->id,
            'author'    => fullname($USER),
        ], $uploadrepository->id, $object);

        printf(", 已完成\n<br/>");
    }
    echo $OUTPUT->continue_button(new moodle_url('/mod/folder/view.php', array('id' => $cm->id)));
}

// Finish the page.
echo $OUTPUT->footer();

/**
 * Get OSS
 *
 * @return oss
 */
function create_oss($repository) {
    global $_ossclient;
    if ($_ossclient == null) {
        $access_key = $repository->get_option('access_key');
        $secret_key = $repository->get_option('secret_key');
        $endpoint = $repository->get_option('endpoint');
        if (empty($access_key)) {
            throw new moodle_exception('needaccesskey', 'repository_ossbucket');
        }
        try {
            $ossClient = new OssClient($access_key, $secret_key, 'https://'.$endpoint.'.aliyuncs.com', false);                
        } catch (OssException $e) {
            debugging("Warning: creating OssClient instance: FAILED".$e->getMessage());
            return null;
        }

        return $ossClient;
    }
    return $_ossclient;
}
