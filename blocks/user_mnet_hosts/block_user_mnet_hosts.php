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

require_once($CFG->dirroot.'/blocks/user_mnet_hosts/locallib.php');

class block_user_mnet_hosts extends block_list {

    function init() {
        $this->title = get_string('user_mnet_hosts', 'block_user_mnet_hosts') ;
    }

    function has_config() {
        return true;
    }

    function applicable_formats() {
        return array('all' => true, 'mod' => false, 'tag' => false, 'my' => true);
    }

    function get_content() {
        global $THEME, $CFG, $USER, $PAGE, $OUTPUT, $DB, $SESSION, $COURSE;

        if (empty($CFG->block_u_m_h_displaylimit)) {
            set_config('block_u_m_h_displaylimit', 40);
        }

        $PAGE->requires->js('/blocks/user_mnet_hosts/js/jump.js');

        // Only for logged in users!
        if (!isloggedin() || isguestuser()) {
            return false;
        }

        // Impeach local administrator to roam elsewhere.
        if (($USER->username == 'admin') && ($USER->auth == 'manual') && empty($CFG->user_mnet_hosts_admin_override)) {
            $this->content = new StdClass();
            $this->content->footer = $OUTPUT->notification(get_string('errorlocaladminconstrainted', 'block_user_mnet_hosts'));
            return $this->content;
        }

        if (!is_enabled_auth('multimnet') && !is_enabled_auth('mnet')) {
            // No need to query anything remote related.
            $this->content = new StdClass();
            $this->content->footer = $OUTPUT->notification(get_string('errormnetauthdisabled', 'block_user_mnet_hosts'));
            return $this->content;
        }

        $systemcontext = context_system::instance();

        // Check for outgoing roaming permission first.
        if (!has_capability('moodle/site:mnetlogintoremote', $systemcontext, NULL, false)) {
            if (has_capability('moodle/site:config', $systemcontext)) {
                $this->content = new StdClass();
                $this->content->footer = get_string('errornocapacitytologremote', 'block_user_mnet_hosts');
            }
            return '';
        }

        if ($this->content !== NULL) {
            return $this->content;
        }

        $hosts = user_mnet_hosts_get_hosts();
        $mnet_accesses = user_mnet_hosts_get_access_fields();

        $this->content = new StdClass();
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        if ($hosts) {
            $maxhosts = count($hosts);
            $i = 0;
            $j = 0;
            foreach ($hosts as $host) {
                $i++;
                if ($maxhosts > $CFG->block_u_m_h_displaylimit) {

                    $SESSION->umhfilter = optional_param('umhfilter', @$SESSION->umhfilter, PARAM_TEXT);

                    if (!empty($SESSION->umhfilter)) {
                        if (!preg_match('/'.preg_quote($SESSION->umhfilter).'/', $host->name)) {
                            continue;
                        }
                    }
                }

                // i all hosts / j visible only
                $j++;
                if (($maxhosts > $CFG->block_u_m_h_displaylimit) && ($j >= $CFG->block_u_m_h_displaylimit)) {
                    if ($i < $maxhosts) {
                        $this->content->icons[] = '';
                        $this->content->items[] = get_string('usefiltertoreduce', 'block_user_mnet_hosts');
                    }
                    break;
                }

                // Implement user access filtering.
                $hostaccesskey = strtolower(user_mnet_hosts_make_accesskey($host->wwwroot, false));

                if ($host->application == 'moodle' || empty($CFG->block_u_m_h_maharapassthru)) {
                    if (empty($mnet_accesses[$hostaccesskey]) && !has_capability('block/user_mnet_hosts:accessall', context_system::instance())) {
                        continue;
                    }
                }

                $icon  = '<img src="'.$OUTPUT->pix_url('/i/'.$host->application.'_host').'" class="icon" alt="'.get_string('server', 'block_mnet_hosts').'" />';

                $this->content->icons[] = $icon;

                $cleanname = preg_replace('/^https?:\/\//', '', $host->name);
                $cleanname = str_replace('.', '', $cleanname);
                $target = '';
                if (@$CFG->user_mnet_hosts_new_window) {
                    $target = " target=\"{$cleanname}\" ";
                    $target = " target=\"_blank\" ";
                }

                if ($host->id == $USER->mnethostid) {
                    $this->content->items[]="<a title=\"" .s($host->name).
                        "\" href=\"{$host->wwwroot}\" $target >". s($host->name) ."</a>";
                } else {
                    if (is_enabled_auth('multimnet')) {
                        $this->content->items[]="<a title=\"" .s($host->name).
                            "\" href=\"javascript:multijump('$CFG->wwwroot','$host->id')\">" . s($host->name) ."</a>";
                    } else {
                        $this->content->items[]="<a title=\"" .s($host->name).
                            "\" href=\"javascript:standardjump('$CFG->wwwroot','$host->id')\">" . s($host->name) ."</a>";
                    }
                }
            }
        } else {
            $this->content->footer = $OUTPUT->notification(get_string('nohostsforyou', 'block_user_mnet_hosts'));
        }
        if (count($hosts) > $CFG->block_u_m_h_displaylimit) {
            $footer = '<form name="umhfilterform" action="#">';
            $footer .= '<input type="hidden" name="id" value="'.$COURSE->id.'" />';
            $footer .= '<input class="form-minify" type="text" name="umhfilter" value="'.(@$SESSION->umhfilter).'" />';
            $footer .= '<input class="form-minify" type="submit" name="go" value="'.get_string('filter', 'block_user_mnet_hosts').'" />';
            $footer .= '</form>';
            $this->content->footer = $footer;
        }
        return $this->content;
    }

    // RPC dedicated functions
    /**
     * checks locally if an incoming user has remote provision to come in  
     * Call needs to be hooked on "login" access (and mnet landing) to
     * avoid back door effect.
     * Called by : the landing node
     * Checked in : the local node
     * @param $remoteuser : structure containing username, userremoteroot identity
     * @param $fromwwwroot : remote caller identity
     *
     * TODO : implement this security check.
     * Register it
     */
    
    function remote_user_mnet_check($remoteuser, $fromwwwwroot) {
    }
}

