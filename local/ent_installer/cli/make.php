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

$initialroot = dirname(__FILE__);

global $make;
global $checks;
global $steps;

$processUser = posix_getpwuid(posix_geteuid());

if ($user = $processUser['name'] !== 'root') {
    print $processUser['name'];
    die("Only root can use this script\n");
}

# Getting args[1] as special step to run

$step = 0;
if (!empty($argv[1])) {
    $step = $argv[1];
}

include 'make_config.php';

### Package install and check

include 'makefile.php';

if ($step) {
    if (is_numeric($step)){
        echo "Running single step $step: $steps[$step]\n";
        run($step);
    } else {
        if (preg_match('/^(\d+)-$/', $step, $matches)) {
            $from = $matches[1];
            echo "Running from step $from: $steps[$from]\n";
            run($from, true);
        } elseif (preg_match('/^-(\d+)$/', $step, $matches)) {
            $to = $matches[1];
            echo "Running all until step $to: $steps[$to]\n";
            run(0, true, $to);
        } elseif (preg_match('/^(\d+)-(\d+)$/', $step, $matches)) {
            $from = $matches[1];
            $to = $matches[2];
            echo "Running from $from: $steps[$from] to $to: $steps[$to]\n";
            run($from, true, $to);
        }
    }
} else {
    echo "Running full install process\n";
    // run();
}

function run($runstep  = 0, $continue = false, $stopat = 0) {
    global $steps;

    if ($runstep && !$continue) {
        run_step($runstep);
    } elseif($runstep && $continue) {
        for ($step = $runstep;;$step++) {
            if ($stopat && ($stopat == $step)) {
                die('Stop clause reached. Stopping at step '.$step."\n");
            }
            echo "Running step $step: $steps[$step]\n";
            run_step($step);
        }
    } else {
        for ($step = 1;;$step++) {
            if ($stopat && ($stopat == $step)) {
                die('Stop clause reached. Stopping at step '.$step."\n");
            }
            echo "Running step $step: $steps[$step]\n";
            run_step($step);
        }
    }
}

function run_step($step) {
    global $make;
    global $checks;

    // Empty step will close installer.
    if (!isset($checks[$step]) && !isset($make[$step])) {
        echo "Make finished. Resuming...\n";
        die;
    }
    
    if (isset($checks[$step])) {
        foreach ($checks[$step] as $check) {
            list($cmdlabel, $type, $checkpath) = $check;
            echo "Evaluating $cmdlabel\n";
            switch ($type) {
                case 'file':
                    if (!is_file($checkpath)) {
                        die ("Error on step $step : $cmdlabel\nCheck failed on $checkpath\n");
                    }
                    break;
                case 'dir':
                    if (!is_dir($checkpath)) {
                        die ("Error on step $step : $cmdlabel\nCheck failed on $checkpath\n");
                    }
                    break;
                case 'grep':
                    exec($checkpath, $output, $return);
                    if (empty($output)) {
                        die ("Error on step $step : $cmdlabel\nExpected sequence not found in $checkpath\n");
                    }
                    break;
                default:
                    die ("Error on step $step : $cmdlabel\nBad check type\n");
            }
        }
    }

    if (isset($make[$step])) {
        foreach ($make[$step] as $makecmd) {
            list($makename, $type, $cmd) = $makecmd;
            switch ($type) {
                case 'skipexists':
                    exec($cmd, $output, $result);
                    // echo "\t".implode($output, "\t\n");
                    break;
                case 'skipfail':
                    exec($cmd, $output, $result);
                    echo "\t".implode($output, "\t\n");
                    break;
                case 'changedir':
                    if (!chdir($cmd)) {
                        die ("Error on step $step : $makename\ncould not change dir to $cmd\n");
                    }
                    break;
                case 'stopformanual':
                    die ("This step $step is interactive and cannot be automated : $cmd\n");
                    break;
                default:
                    // this stands for 'stoponfailure'
                    exec($cmd, $output, $result);
                    echo "\t".implode($output, "\t\n");
                    if ($result != 0) {
                        die ("Shell error on step $step : $makename\ncould not execute $cmd\n");
                    }
            }
        }
    }
}