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
 * Define all the restore steps that will be used by the restore opencast block task.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

global $CFG;

use block_opencast\local\event;
use block_opencast\local\notifications;

/**
 * Define all the restore steps that will be used by the restore opencast block task.
 *
 * @package    block_opencast
 * @copyright  2018 Andreas Wagner, SYNERGY LEARNING
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_opencast_block_structure_step extends restore_structure_step {

    private $backupeventids = [];
    private $missingeventids = [];
    private $seriesid = null;

    /**
     * Function that will return the structure to be processed by this restore_step.
     *
     * @return array of @restore_path_element elements
     */
    protected function define_structure() {
        global $USER;

        // Check, target series.
        $courseid = $this->get_courseid();

        $paths = array();

        // Get apibridge instance.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Get the import mode to decide the way of importing opencast videos
        $importmode = get_config('block_opencast', 'importmode');
        $this->importmode = $importmode;

        // If ACL Change is the mode.
        if ($importmode == 'acl') {
            // We process the rest in the process import.
            $paths[] = new restore_path_element('import', '/block/opencast/import');
            
        } else if ($importmode == 'duplication') {
            // In case Duplicating Events is the mode.

            // Get series id.
            $seriesid = $apibridge->get_stored_seriesid($courseid, true, $USER->id);
            // If seriesid does not exist, we create one.
            if (!$seriesid) {
                // Make sure to create using another method.
                $seriesid = $apibridge->create_course_series($courseid, null, $USER->id);
            }
            $this->seriesid = $seriesid;

            $paths[] = new restore_path_element('event', '/block/opencast/events/event');
        }

        return $paths;
    }

    /**
     * Process the backuped data.
     *
     * @param array $data the event identifier
     * @return void
     */
    public function process_event($data) {

        $data = (object) $data;

        // Collect eventids for notification.
        $this->backupeventids[] = $data->eventid;

        // Exit when there is no course series.
        if (!$this->seriesid) {
            return;
        }

        // Check, whether event exists on opencast server.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Only duplicate, when the event exists in opencast.
        if (!$apibridge->get_already_existing_event([$data->eventid])) {
            $this->missingeventids[] = $data->eventid;
        } else {
            $courseid = $this->get_courseid();
            event::create_duplication_task($courseid, $this->seriesid, $data->eventid);
        }
    }

    /**
     * Process the backuped data for import.
     *
     * @param array $data The import data needed for ACL change mode.
     * @return void
     */
    public function process_import($data) {
        global $USER;

        $data = (object) $data;

        // Check, target series.
        $courseid = $this->get_courseid();

        // Get apibridge instance, to ensure series validity and edit series mapping.
        $apibridge = \block_opencast\local\apibridge::get_instance();

        // Exit when there is no original series, no course course id and the original seriesid is not valid.
        // Also exit when the course by any chance wanted to resote itself.
        if (!$data->seriesid && !$data->sourcecourseid && $apibridge->ensure_series_is_valid($data->seriesid) && $courseid == $data->sourcecourseid) {
            return;
        }

        // Collect sourcecourseid for notifications.
        $this->sourcecourseid = $data->sourcecourseid;

        // Collect series id for notifications.
        $this->seriesid = $data->seriesid;

        // Assign Seriesid to new course and change ACL.
        $this->aclchanged = $apibridge->import_series_to_course_with_acl_change($courseid, $data->seriesid, $data->sourcecourseid, $USER->id);
     }


    /**
     * Send notifications after restore, to inform admins about errors.
     *
     * @return void
     */
    public function after_restore() {

        $courseid = $this->get_courseid();

        // Import mode is not defined.
        if (!$this->importmode) {
            notifications::notify_failed_importmode($courseid);
            return;
        }

        if ($this->importmode == 'duplication') {
            // None of the backupeventids are used for starting a workflow.
            if (!$this->seriesid) {
                notifications::notify_failed_course_series($courseid, $this->backupeventids);
                return;
            }

            // A course series is created, but some events are not found on opencast server.
            if ($this->missingeventids) {
                notifications::notify_missing_events($courseid, $this->missingeventids);
            }
        } else if ($this->importmode == 'acl') {
            // The required data or the conditions to perform ACL change were missing.
            if (!$this->sourcecourseid) {
                notifications::notify_missing_sourcecourseid($courseid);
                return;
            }

            if (!$this->seriesid) {
                notifications::notify_missing_seriesid($courseid);
                return;
            }
            // The ACL change import process is not successful.
            if ($this->aclchanged->error == 1) {
                if (!$this->aclchanged->seriesaclchange) {
                    notifications::notify_failed_series_acl_change($courseid, $this->sourcecourseid);
                    return;
                }

                if (!$this->aclchanged->eventsaclchange && count($this->aclchanged->eventsaclchange->failed) > 0) {
                    notifications::notify_failed_events_acl_change($courseid, $this->sourcecourseid, $this->aclchanged->eventsaclchange->failed);
                    return;
                }

                if (!$this->aclchanged->seriesmapped) {
                    notifications::notify_failed_series_mapping($courseid, $this->seriesid);
                }
            }
        }
    }

}
