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
 * Import Microsoft Word file form.
 *
 * @package   local_course_folderfileimport
 * @copyright Martin Liao
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* This file contains code based on mod/book/tool/importhtml/import_form.php
 * (copyright 2004-2011 Petr Skoda) from Moodle 2.4. */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . DIRECTORY_SEPARATOR . 'formslib.php');

class local_course_folderfileimport_form extends moodleform {

    /**
     * Define Microsoft Excel file import form
     * 定义Excel导入表单
     * @return void
     */
    public function definition() {
        $mform = $this->_form;
        $data  = $this->_customdata;

        $mform->addElement('header', 'general', get_string('excelimport', 'local_course_folderfileimport'));

        // User can select 1 and only 1 Excel file which must have a .csv suffix (not xls).
        $mform->addElement('filepicker', 'importfile', get_string('filetoimport', 'local_course_folderfileimport'), null,
                           array('subdirs' => 0, 'accepted_types' => array('.csv')));
        $mform->addHelpButton('importfile', 'filetoimport', 'local_course_folderfileimport');
        $mform->addRule('importfile', null, 'required', null, 'client');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('hidden', 'action');
        $mform->setType('action', PARAM_TEXT);

        $this->add_action_buttons(true, get_string('import'));
        $this->set_data($data);
    }

    /**
     * Define Excel import form validation
     * Excel导入表单检查
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;

        if ($errors = parent::validation($data, $files)) {
            return $errors;
        }

        $usercontext = context_user::instance($USER->id);
        $fs = get_file_storage();

        if (!$files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['importfile'], 'id', false)) {
            $errors['importfile'] = get_string('required');
            return $errors;
        } else {
            $file = reset($files);
            $mimetype = $file->get_mimetype();
            if ($mimetype != 'text/csv') {
                $errors['importfile'] = get_string('invalidfiletype', 'error', $file->get_filename());
                $fs->delete_area_files($usercontext->id, 'user', 'draft', $data['importfile']);
            }
        }

        return $errors;
    }
}


class local_folder_fileupload_form extends moodleform {

    /**
     * 定义文件上传表单
     * @return void
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;
        $data  = $this->_customdata;

        $mform->addElement('header', 'general', '文件上传到AliyunOSS');

        $fileoptions = array('subdirs'=>0,
                                'maxbytes' => 100,  
                                'accepted_types'=>'*',
                                'maxfiles'=>20,
                                'return_types'=>FILE_INTERNAL); //FILE_INTERNAL
        $mform->addElement('filemanager', 'uploadfiles', '文件导入', null, $fileoptions);
        $mform->addHelpButton('uploadfiles', 'filetoimport', 'local_course_folderfileimport');
        $mform->addRule('uploadfiles', null, 'required', null, 'client');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons(true, '上传');
        $this->set_data($data);
    }


}
