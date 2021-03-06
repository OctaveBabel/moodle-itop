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

require_once($CFG->dirroot."/mod/customlabel/type/customtype.class.php");

class customlabel_type_unitheading extends customlabel_type{

    function __construct($data) {
        global $CFG, $COURSE, $PAGE;

        parent::__construct($data);
        $this->type = 'unitheading';
        $this->fields = array();

        $field = new StdClass;
        $field = new StdClass;
        $field->name = 'heading';
        $field->size = 80;
        $field->type = 'textfield';
        $this->fields['heading'] = $field;

        $field = new StdClass;
        $field->name = 'shortdesc';
        $field->type = 'textarea';
        $this->fields['shortdesc'] = $field;

        $field = new StdClass();
        $field->name = 'image';
        $field->type = 'filepicker';
        $field->destination = 'url';
        if ($PAGE->state >= moodle_page::STATE_IN_BODY) {
            if (!is_file($CFG->dirroot.'/theme/'.$PAGE->theme->name.'/pix/customlabel_icons/defaultunitheading.png')) {
                $field->default = $CFG->wwwroot.'/mod/customlabel/type/sectionheading/defaultunitheading.jpg';
            } else {
                $field->default = $CFG->wwwroot.'/theme/'.$PAGE->theme->name.'/pix/customlabel_icons/defaultunitheading.png';
            }
        } else {
            $field->default = $CFG->wwwroot.'/mod/customlabel/type/unitheading/defaultsectionheading.jpg';
        }
        $this->fields['image'] = $field;

        $field = new StdClass();
        $field->name = 'overimagetext';
        $field->type = 'textfield';
        $field->size = 20;
        $this->fields['overimagetext'] = $field;

        $field = new StdClass();
        $field->name = 'imageposition';
        $field->type = 'list';
        $field->options = array('none', 'left', 'right');
        $field->default = 'left';
        $this->fields['imageposition'] = $field;

    }

    /**
     * If exists, this method can process local alternative values for
     * realizing the template, after all standard translations have been performed. 
     * Type information structure and application context dependant.
     */
    function postprocess_data($course = null) {
        global $CFG;

        // Get virtual fields from course title.
        $storedimage = $this->get_file_url('image');
        $imageurl = (!empty($storedimage)) ? $storedimage : $this->fields['image']->default;
        if ($this->data->imagepositionoption == 'left') {
            $this->data->imageL = "<td width=\"100\" class=\"custombox-icon-left unitheading\" align=\"center\" style=\"background:url({$imageurl}) 50% 50% no-repeat transparent\">{$this->data->overimagetext}</td>";
            $this->data->imageR = '';
        } elseif ($this->data->imagepositionoption == 'right') {
            $this->data->imageL = '';
            $this->data->imageR = "<td width=\"100\" class=\"custombox-icon-right unitheading\" align=\"center\" style=\"background:url({$imageurl}) 50% 50%  no-repeat transparent\">{$this->data->overimagetext}</td>";
        } else {
            $this->data->imageL = '';
            $this->data->imageR = '';
        }
    }
}