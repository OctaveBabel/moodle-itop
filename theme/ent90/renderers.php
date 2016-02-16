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
 * Renderers to align Moodle's HTML with that expected by Bootstrap
 *
 * @package    theme_ent90
 * @copyright  2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class theme_ent90_core_renderer extends core_renderer {

    /*
     * This renders a notification message.
     * Uses bootstrap compatible html.
     */
     
    public function custom_menu($custommenuitems = '') {
        global $CFG;

        if (empty($custommenuitems) && !empty($CFG->custommenuitems)) {
            $custommenuitems = $CFG->custommenuitems;
        }
        $custommenu = new custom_menu($custommenuitems, current_language());
        return $this->render_custom_menu($custommenu);
    }

    /*
     * This renders the bootstrap top menu.
     *
     * This renderer is needed to enable the Bootstrap style navigation.
     */
    protected function render_custom_menu(custom_menu $menu) {
        global $CFG;

        // TODO: eliminate this duplicated logic, it belongs in core, not
        // here. See MDL-39565.
        $addlangmenu = true;
        $langs = get_string_manager()->get_list_of_translations();
        if (count($langs) < 2
            or empty($CFG->langmenu)
            or ($this->page->course != SITEID and !empty($this->page->course->lang))) {
            $addlangmenu = false;
        }

        if (!$menu->has_children() && $addlangmenu === false) {
            return '';
        }

        if ($addlangmenu) {
            $strlang =  get_string('language');
            $currentlang = current_language();
            if (isset($langs[$currentlang])) {
                $currentlang = $langs[$currentlang];
            } else {
                $currentlang = $strlang;
            }
            $this->language = $menu->add($currentlang, new moodle_url('#'), $strlang, 10000);
            foreach ($langs as $langtype => $langname) {
                $this->language->add($langname, new moodle_url($this->page->url, array('lang' => $langtype)), $langname);
            }
        }

        $content = '<ul class="nav">';
        foreach ($menu->get_children() as $item) {
            $content .= $this->render_custom_menu_item($item, 1);
        }

        return $content.'</ul>';
    }

    /*
     * This code renders the custom menu items for the
     * bootstrap dropdown menu.
     */
    protected function render_custom_menu_item(custom_menu_item $menunode, $level = 0 ) {
        global $COURSE, $USER, $CFG;
        
        static $submenucount = 0;

        if ($menunode->has_children()) {

            if ($level == 1) {
                $class = 'dropdown';
            } else {
                $class = 'dropdown-submenu';
            }

            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#cm_submenu_'.$submenucount;
            }

            // allow protected menu items to only logged in
            if (preg_match('/^!/', $url)){
                if (!isloggedin() || isguestuser()){
                    return;
            } else {
                    $url = preg_replace('/^!/', '', $url);
                }
            }

            // Url context variables replacement if needed in menu (first catch escaped form).
            $url = preg_replace('/%25WWWROOT%25/', $CFG->wwwroot, $url);
            $url = preg_replace('/%WWWROOT%/', $CFG->wwwroot, $url);
            $url = preg_replace('/%25COURSEID%25/', $COURSE->id, $url);
            $url = preg_replace('/%COURSEID%/', $COURSE->id, $url);
            $url = preg_replace('/%25USERID%25/', $USER->id, $url);
            $url = preg_replace('/%USERID%/', $USER->id, $url);

            if ($menunode === $this->language) {
                $class .= ' langmenu';
            }
            $content = html_writer::start_tag('li', array('class' => $class));
            // If the child has menus render it as a sub menu.
            $submenucount++;
            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#cm_submenu_'.$submenucount;
            }
            $content .= html_writer::start_tag('a', array('href'=>$url, 'class'=>'dropdown-toggle', 'data-toggle'=>'dropdown', 'title'=>$menunode->get_title()));
            $content .= $menunode->get_text();
            if ($level == 1) {
                $content .= '<b class="caret"></b>';
            }
            $content .= '</a>';
            $content .= '<ul class="dropdown-menu">';
            foreach ($menunode->get_children() as $menunode) {
                $content .= $this->render_custom_menu_item($menunode, 0);
            }
            $content .= '</ul>';
        } else {
            $content = '<li>';
            // The node doesn't have children so produce a final menuitem.
            if ($menunode->get_url() !== null) {
                $url = $menunode->get_url();
            } else {
                $url = '#';
            }

            $coursecontext = context_course::instance($COURSE->id);

            // allow protected menu items to only logged in
            if (preg_match('/^([^!]*)!(.*)$/', $url, $matches)) {
                if (empty($matches[1])){
                    if (!isloggedin() || isguestuser()) {
                        return;
                    } else {
                        $url = preg_replace('/^!/', '', $url);
                    }
                } else {
                    if (has_capability($matches[1], $coursecontext)) {
                        $url = $matches[2];
                    } else {
                        return;
                    }
                }
            }

            // Url context variables replacement if needed in menu (first catch escaped form).
            $url = preg_replace('/%25WWWROOT%25/', $CFG->wwwroot, $url);
            $url = preg_replace('/%WWWROOT%/', $CFG->wwwroot, $url);
            $url = preg_replace('/%25COURSEID%25/', $COURSE->id, $url);
            $url = preg_replace('/%COURSEID%/', $COURSE->id, $url);
            $url = preg_replace('/%25USERID%25/', $USER->id, $url);
            $url = preg_replace('/%USERID%/', $USER->id, $url);

            $content .= html_writer::link($url, $menunode->get_text(), array('title'=>$menunode->get_title()));
        }
        return $content;
    }
    public function notification($message, $classes = 'notifyproblem') {
        $message = clean_text($message);
        $type = '';

        if ($classes == 'notifyproblem') {
            $type = 'alert alert-error';
        }
        if ($classes == 'notifysuccess') {
            $type = 'alert alert-success';
        }
        if ($classes == 'notifymessage') {
            $type = 'alert alert-info';
        }
        if ($classes == 'redirectmessage') {
            $type = 'alert alert-block alert-info';
        }
        return "<div class=\"$type\">$message</div>";
    }

    /**
     * Returns a string containing a link to the user documentation.
     * Also contains an icon by default. Shown to teachers and admin only.
     *
     * @param string $path The page link after doc root and language, no leading slash.
     * @param string $text The text to be displayed for the link
     * @param boolean $forcepopup Whether to force a popup regardless of the value of $CFG->doctonewwindow
     * @return string
     */
    public function doc_link($path, $text = '', $title = '', $forcepopup = false) {
        global $CFG;

        $icon = $this->pix_icon('docs', $text, 'moodle', array('class' => 'iconhelp icon-pre', 'title' => $title));

        $url = new moodle_url(get_docs_url($path));

        $attributes = array('href'=>$url);
        if (!empty($CFG->doctonewwindow) || $forcepopup) {
            $attributes['class'] = 'helplinkpopup';
        }

        return html_writer::tag('a', $icon.$text, $attributes);
    }

    /**
     * Returns the Moodle docs link to use for this page.
     *
     * @since 2.5.1 2.6
     * @param string $text
     * @return string
     */
    public function page_doc_link($footext = null) {
        $title = get_string('moodledocslink');
        $path = page_get_doc_link_path($this->page);
        if (!$path) {
            return '';
        }
        return $this->doc_link($path, '', $title);
    }

    /*
     * This code replaces the icons in the Admin block with
     * FontAwesome variants where available.
     */
     protected function render_pix_icon(pix_icon $icon) {
        if (self::replace_moodle_icon($icon->pix) !== false && @$icon->attributes['alt'] === '' && @$icon->attributes['title'] === '') {
            return self::replace_moodle_icon($icon->pix);
        } else {
            return parent::render_pix_icon($icon);
        }
    }

    private static function replace_moodle_icon($name) {
        $icons = array(
            'add' => 'plus',
            'book' => 'book',
            'chapter' => 'file',
            'docs' => 'question-sign',
            'generate' => 'gift',
            'i/backup' => 'upload-alt',
            'i/checkpermissions' => 'user',
            'i/edit' => 'pencil',
            'i/filter' => 'filter',
            'i/grades' => 'table',
            'i/group' => 'group',
            'i/hide' => 'eye-open',
            'i/import' => 'download-alt',
            'i/move_2d' => 'move',
            'i/navigationitem' => 'circle-blank',
            'i/outcomes' => 'magic',
            'i/publish' => 'globe',
            'i/reload' => 'refresh',
            'i/report' => 'list-alt',
            'i/restore' => 'download-alt',
            'i/return' => 'repeat',
            'i/roles' => 'user',
            'i/settings' => 'beaker',
            'i/show' => 'eye-close',
            'i/switchrole' => 'random',
            'i/user' => 'user',
            'i/users' => 'user',
            't/right' => 'arrow-right',
            't/left' => 'arrow-left',
        );
        if (isset($icons[$name])) {
            return "<i class=\"icon-$icons[$name]\" id=\"icon\"></i>";
        } else {
            return false;
        }
    }
}

include_once($CFG->dirroot . "/course/renderer.php");

class theme_ent90_core_course_renderer extends core_course_renderer {
    
    /**
     * Set a css class to each activity item in course module choose lightbox form because parent selector does not exists in css3
     * @param mixed $modules array of objects for each available modules (activities & resources)
     * @param mixed $course object for the current course
     * @return string HTML containing the list of available modules
     */
    public function course_modchooser($modules, $course) {
        $content = parent::course_modchooser($modules, $course);
        
        // Could be done with one call to array_filter(), but we need to keep the same modules order than in parent method
        $modulesfiltered = array_merge(
            array_filter($modules, create_function('$mod', 'return ($mod->archetype !== MOD_ARCHETYPE_RESOURCE && $mod->archetype !== MOD_ARCHETYPE_SYSTEM);')),
            array_filter($modules, create_function('$mod', 'return ($mod->archetype === MOD_ARCHETYPE_RESOURCE);'))
        );
        
        if (count($modulesfiltered)) {
            $html = $content;
            foreach($modulesfiltered as $modulename => $module) {
                if (!isset($module->types)) {
                    $html = preg_replace('/<div class="option">/', ((!empty($modulename)) ? '<div class="option div_module_'.$modulename.'">' : '<div class="option div_module_unknown">'), $html, 1);
                }
                else {
                    $html = preg_replace('/<div class="nonoption">/', ((!empty($modulename)) ? '<div class="nonoption div_module_'.$modulename.'">' : '<div class="nonoption div_module_unknown">'), $html, 1);
                    $html = preg_replace('/<div class="option subtype">/', ((!empty($modulename)) ? '<div class="option subtype div_module_'.$modulename.'">' : '<div class="option subtype div_module_unknown">'), $html, count($module->types));
                }
            }
            return $html;
        }
        return str_replace('<div class="option subtype">', '<div class="option subtype div_module_unknown">', 
            str_replace('<div class="nonoption">', '<div class="nonoption div_module_unknown">', 
                str_replace('<div class="option">', '<div class="option div_module_unknown">', $content)
            )
        );
    }
}
