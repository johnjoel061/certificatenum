<?php
// This file is part of the tool_certificate plugin for Moodle - http://moodle.org/
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
 * This file contains the version information for the code plugin.
 *
 * @package    certificateelement_certificatenumber
 * @copyright  2025 John Joel Alfabete <example@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace certificateelement_certificatenumber;

/**
 * The certificate number code's core interaction API.
 *
 * @package    certificateelement_certificatenumber
 * @copyright  2013 John Joel Alfabete <example@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class element extends \tool_certificate\element {

    const DISPLAY_CERTIFICATENUMBER = 1;

    public function render_form_elements($mform) {
        $options = [
            self::DISPLAY_CERTIFICATENUMBER => get_string('displaycertificatenumber', 'certificateelement_certificatenumber')
        ];

        $mform->addElement('select', 'display', get_string('display', 'certificateelement_certificatenumber'), $options);
        $mform->addHelpButton('display', 'display', 'certificateelement_certificatenumber');
        $mform->setDefault('display', self::DISPLAY_CERTIFICATENUMBER);
        parent::render_form_elements($mform);
        $mform->setDefault('width', 35);
    }

    public function save_form_data(\stdClass $data) {
        $data->data = json_encode(['display' => $data->display]);
        parent::save_form_data($data);
    }

    /**
     * Generates a unique certificate number.
     *
     * @return int
     */
    protected function generate_certificate_number() {
        global $DB;

        try {
            $maxnum = $DB->get_field_sql("SELECT MAX(certificatenumber) FROM {tool_certificate_number}");
            $nextNumber = $maxnum !== NULL ? $maxnum + 1 : 1;
            return $nextNumber;
        } catch (\Exception $e) {
            debugging('Error generating certificate number: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0; // Fallback certificate number.
        }
    }

    /**
     * Inserts certificate issue details into tool_certificate_number with a unique certificate number.
     *
     * @param int $issueid
     * @return int The assigned certificate number.
     */
    protected function certificate_number_db_insert($issueid) {
        global $DB;

        // Generate a new certificate number.
        $certificatenumber = $this->generate_certificate_number();

        // Fetch data from tool_certificate_issues.
        $issue = $DB->get_record('tool_certificate_issues', ['id' => $issueid], 'id, code, timecreated, expires');

        if ($issue) {
            $record = new \stdClass();
            $record->certificatenumber = $certificatenumber;
            $record->issueid = $issue->id;
            $record->code = $issue->code;
            $record->timecreated = $issue->timecreated;
            $record->expires = $issue->expires;

            $DB->insert_record('tool_certificate_number', $record);
        }

        return $certificatenumber;
    }

    /**
     * Handles rendering the element on the PDF.
     *
     * @param \pdf $pdf the PDF object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     * @param \stdClass $issue the issue we are rendering
     */
    public function render($pdf, $preview, $user, $issue) {
        $data = json_decode($this->get_data());

        if (isset($data->display) && $data->display == self::DISPLAY_CERTIFICATENUMBER) {
            $certificatenumber = $this->certificate_number_db_insert($issue->id);
            \tool_certificate\element_helper::render_content($pdf, $this, $certificatenumber);
        } else {
            \tool_certificate\element_helper::render_content($pdf, $this, $issue->code);
        }
    }

    public function render_html() {
        $data = json_decode($this->get_data(), true);
        $code = $this->generate_certificate_number();
        return \tool_certificate\element_helper::render_html_content($this, $code);
    }

    public function prepare_data_for_form() {
        $record = parent::prepare_data_for_form();
        if ($this->get_data()) {
            $dateinfo = json_decode($this->get_data());
            if (isset($dateinfo->display)) {
                $record->display = $dateinfo->display;
            }
        }
        return $record;
    }

    public function get_width(): int {
        $width = $this->persistent->get('width');
        return $width > 0 ? $width : 35;
    }
}

