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

    /**
     * @var int Option to display certificate number
     */
    const DISPLAY_CERTIFICATENUMBER = 1;

    /**
     * This function renders the form elements when adding a certificate element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {

        // Get the possible date options.
        $options = [];
        // New option for certificate number
        $options[self::DISPLAY_CERTIFICATENUMBER] = get_string('displaycertificatenumber', 'certificateelement_certificatenumber');

        $mform->addElement('select', 'display', get_string('display', 'certificateelement_certificatenumber'), $options);
        $mform->addHelpButton('display', 'display', 'certificateelement_certificatenumber');
        $mform->setDefault('display', self::DISPLAY_CERTIFICATENUMBER);

        parent::render_form_elements($mform);
        $mform->setDefault('width', 35);
    }

    /**
     * Saves the form data and stores the issued certificate data into the custom plugin database.
     *
     * @param object $data Form data
     */
    public function save_form_data($data) {
        global $DB;
    
        parent::save_form_data($data); // Save original data
    
        // Check if $data->id is set
        if (!isset($data->id)) {
            error_log("Error: Certificate ID is not set.");
            return;
        }
    
        $certificateid = $data->id;
    
        // Retrieve issued certificate details
        $certificate = $DB->get_record('tool_certificate_issues', ['id' => $certificateid]);
    
        if (!$certificate) {
            error_log("Error: No certificate found with ID $certificateid in tool_certificate_issues.");
            return;
        }
    
        // Check if the record already exists
        $exists = $DB->record_exists('tool_certificate_number', ['certificateid' => $certificate->id]);
    
        if ($exists) {
            error_log("Certificate ID $certificateid already exists in tool_certificate_number. Skipping insert.");
            return;
        }
    
        // Prepare the record for insertion
        $record = new \stdClass();
        $record->certificateid = $certificate->id;
        $record->userid = $certificate->userid;
        $record->courseid = $certificate->courseid;
        $record->templateid = $certificate->templateid;
        $record->code = $certificate->code;
        $record->emailed = $certificate->emailed;
        $record->timecreated = $certificate->timecreated;
        $record->expires = $certificate->expires;
        $record->data = $certificate->data;
        $record->component = $certificate->component;
        $record->archived = $certificate->archived;
        $record->certificatenumber = $certificate->certificatenumber;
    
        // Log the record before inserting
        error_log("Record to insert: " . print_r($record, true));
    
        // Insert the record
        $result = $DB->insert_record('tool_certificate_number', $record);
    
        if (!$result) {
            error_log("Error: Failed to insert record for certificate ID $certificateid.");
        } else {
            error_log("Success: Inserted certificate ID $certificateid into tool_certificate_number.");
        }
    }
    
    /**
     * Formats a code according to current display value
     *
     * @param string $code
     * @return string
     */
    protected function format_code($code) {
        $data = json_decode($this->get_data());
        switch ($data->display) {
            // Uses the given $code to generate the next certificate number.
            case self::DISPLAY_CERTIFICATENUMBER:
                $display = $this->generate_certificate_number($code);
                break;
            default:
                $display = $code;
        }

        return $display;
    }

    /**
     * Generate a sequential certificate code based on the database.
     *
     * @return string
     */
    protected function generate_certificate_number() {
        global $DB;

        // Check if the certificatenumber column exists.
        $columns = $DB->get_columns('tool_certificate_issues');

        if (!isset($columns['certificatenumber'])) {
            // Column doesn't exist, add it to the database.
            $DB->execute("ALTER TABLE {tool_certificate_issues} ADD COLUMN certificatenumber INT(11) NOT NULL DEFAULT 0");
        }

        // Get the highest certificate number from the database.
        $maxNum = $DB->get_field_sql("SELECT MAX(CAST(certificatenumber AS SIGNED)) FROM {tool_certificate_issues}");

        // If no code exists, start from 1; otherwise, increment the highest value.
        $newNumber = ($maxNum === null || $maxNum < 1) ? 1 : ((int)$maxNum + 1);

        return $newNumber;
    }
    
    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     * @param \stdClass $issue the issue we are rendering
     */
    public function render($pdf, $preview, $user, $issue) {
        global $DB;

        $data = json_decode($this->get_data());
    
        if ($data->display == self::DISPLAY_CERTIFICATENUMBER) {
           
             // Ensure the issue has a certificate number
            if (!$issue->certificatenumber) {
                $this->assign_certificate_number($issue->id);
                $issue->certificatenumber = $DB->get_field('tool_certificate_issues', 'certificatenumber', ['id' => $issue->id]);
            }
            
            \tool_certificate\element_helper::render_content($pdf, $this, $issue->certificatenumber);
        } else {
            \tool_certificate\element_helper::render_content($pdf, $this, $this->format_code($issue->code));
        }
    }
    
    /**
     * Reorders sequential codes when a certificate is deleted.
     */
    public function reorder_certificate_numbers() {
        global $DB;

        // Get all issued certificates ordered by certificate number.
        $certificates = $DB->get_records_sql("SELECT id FROM {tool_certificate_issues} ORDER BY certificatenumber ASC");

        $counter = 1;
        foreach ($certificates as $cert) {
            $DB->execute("UPDATE {tool_certificate_issues} SET certificatenumber = ? WHERE id = ?", [$counter, $cert->id]);
            $counter++;
        }
    }

    /**
     * Assigns a new certificate number when issuing a certificate.
     *
     * @param int $issueId The ID of the issued certificate.
     */
    public function assign_certificate_number($issueId) {
        global $DB;

        // Generate a new certificate number.
        $certificateNumber = $this->generate_certificate_number();

         // Ensure uniqueness by checking if the number already exists
        while ($DB->record_exists('tool_certificate_issues', ['certificatenumber' => $certificateNumber])) {
            $certificateNumber++;
        }

        // Ensure the certificate number is always greater than 0.
        if ($certificateNumber <= 0) {
            $certificateNumber = 1;
        }
        
        // Update the database with the new certificate number.
        $DB->execute("UPDATE {tool_certificate_issues} SET certificatenumber = ? WHERE id = ?", [$certificateNumber, $issueId]);
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it.
     *
     * @return string the html
     */
    public function render_html() {
        $data = json_decode($this->get_data(), true);
        $code = \tool_certificate\certificate::generate_code();
        return \tool_certificate\element_helper::render_html_content($this, $this->format_code($code));
    }

    /**
     * Prepare data to pass to moodleform::set_data()
     *
     * @return \stdClass|array
     */
    public function prepare_data_for_form() {
        $record = parent::prepare_data_for_form();
        if ($this->get_data()) {
            $dateinfo = json_decode($this->get_data());
            $record->display = $dateinfo->display;
        }
        return $record;
    }

    /**
     * Returns the width.
     *
     * @return int
     */
    public function get_width(): int {
        $width = $this->persistent->get('width');
        return $width > 0 ? $width : 35;
    }
    
}


