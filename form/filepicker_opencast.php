<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
/**
 * A moodle form filemanager with the possibility to override site and course wide upload limitations.
 *
 * @author Tobias Reischmann
 * @package block
 * @subpackage opencast
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
global $CFG;
require_once("$CFG->libdir/form/filepicker.php");


class MoodleQuickForm_filepicker_opencast extends MoodleQuickForm_filepicker {

    /**
     * Constructor
     *
     * @param string $elementName (optional) name of the filepicker
     * @param string $elementLabel (optional) filepicker label
     * @param array $attributes (optional) Either a typical HTML attribute string
     *              or an associative array
     * @param array $options set of options to initalize filepicker
     */
    public function __construct($elementName=null, $elementLabel=null, $attributes=null, $options=null) {
        global $PAGE;
        parent::__construct($elementName, $elementLabel, $attributes, $options);
        $this->_options['maxbytes'] = get_user_max_upload_file_size($PAGE->context, -1, -1, $options['maxbytes']);
    }

}
// Register wikieditor.
MoodleQuickForm::registerElementType('filepicker_opencast',
    $CFG->dirroot . "/blocks/opencast/form/filepicker_opencast.php", 'MoodleQuickForm_filepicker_opencast');