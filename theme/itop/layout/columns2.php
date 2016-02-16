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
 * The two column layout.
 *
 * @package   theme_itop
 * @copyright 2013 Moodle, moodle.org
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Get the HTML for the settings bits.
$html = theme_itop_get_html_for_settings($OUTPUT, $PAGE);

$left = (!right_to_left());  // To know if to add 'pull-right' and 'desktop-first-column' classes in the layout for LTR.
echo $OUTPUT->doctype() ?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo $OUTPUT->page_title(); ?></title>
    <link rel="shortcut icon" href="<?php echo $OUTPUT->favicon(); ?>" />
    <?php echo $OUTPUT->standard_head_html() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $PAGE->requires->js(new moodle_url('/local/ent_installer/js/itop.js')); ?>
</head>

<body <?php echo $OUTPUT->body_attributes('two-column'); ?>>

<?php 
if (file_exists($CFG->dirroot.'/local/technicalsignals/lib.php'))
	local_print_administrator_message();
echo $OUTPUT->standard_top_of_body_html();

include('includes/header.php');
?>
<div id="page" class="container-fluid">

    <?php include('includes/page_header.php'); ?>

    <div id="page-content" class="row-fluid">
        <section id="region-main" class="span9<?php if ($left) { echo ' pull-right'; } ?>">
            <?php
            echo $OUTPUT->course_content_header();
            echo $OUTPUT->main_content();
            echo $OUTPUT->course_content_footer();
            ?>
        </section>
        <?php
        $classextra = '';
        if ($left) {
            $classextra = ' desktop-first-column';
        }
        echo $OUTPUT->blocks('side-pre', 'span3'.$classextra);
        ?>
    </div>

    <?php include 'includes/footer.php'; ?>
    <?php echo $OUTPUT->standard_end_of_body_html() ?>

</div>
</body>
</html>
