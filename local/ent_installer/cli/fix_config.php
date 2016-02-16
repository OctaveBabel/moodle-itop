<?php

// this is a root script intended to add to the generated config file of Moodle the additional snippet
// of config code that provides additional settings and virtualisation hooking.

$configfilelocation = dirname(dirname(dirname(dirname(__FILE__)))).'/config.php';
$configsavefilelocation = dirname(dirname(dirname(dirname(__FILE__)))).'/config.save.php';

if (!file_exists($configfilelocation)) {
    die("Cannot find Moodle config file at $configfilelocation");
}

$processUser = posix_getpwuid(posix_geteuid());

if (($processUser['name'] !== 'root') && ($processUser['name'] !== 'lmsadm')) {
    print $processUser['name'];
    die("Only root or LMS owner lmsadm can use this script\n");
}

$configfile = implode('', file($configfilelocation));

$origin = "require_once(dirname(__FILE__) . '/lib/setup.php');";

$replacement = "
\$CFG->dirroot = '/app/prodscripts/moodle24-ene-atrium/moodle24-ene-atrium';

\$CFG->dataexchangesafekeys = 'globaladminmessage,globaladminmessagecolor';
\$CFG->mainhostprefix = 'https://commun';

// this allows some vmoodle enabled CLI scripts to get basic configuration
// before switching to vmoodle virtual configuration
if (isset(\$CLI_VMOODLE_PRECHECK) && \$CLI_VMOODLE_PRECHECK == true){
    \$CLI_VMOODLE_PRECHECK = false;
    return;
}

\$CFG->customscripts = \$CFG->dirroot.'/customscripts';
require_once \$CFG->dirroot.'/local/libloader.php';

\$CFG->directorypermissions = 0777;

// forces https proto as x_forwarded when proxy is not able to setup this header field in request.
\$CFG->vmoodle_force_https_proto = true;
\$CFG->sslproxy = true;

require_once \$CFG->dirroot.'/blocks/vmoodle/vconfig.php';

if (preg_match('/^https?:\/\/commun/', \$CFG->wwwroot)) {
    \$CFG->user_mnet_hosts_admin_override = true;
}

//after vconfig to dispatch trace on the adequate moodledata
require_once(\$CFG->dirroot.'/auth/cas/CAS/CAS.php');
phpCAS::setDebug('/data/log/moodle/cas.log');

require_once(\$CFG->dirroot.'/local/libloader.php');
\$CFG->customscripts = \$CFG->dirroot.'/customscripts/';

// metadata defaults
\$CFG->METADATATREE_DEFAULTS['1_1_1']['default'] = 'ENE Atrium - Nice - Aix/Marseille';
\$CFG->METADATATREE_DEFAULTS['1_3']['default'] = 'fra';
\$CFG->METADATATREE_DEFAULTS['3_4']['default'] = 'fra';
\$CFG->METADATATREE_DEFAULTS['5_1']['default'] = 'expositive';
\$CFG->METADATATREE_DEFAULTS['5_5']['default'] = 'student';
\$CFG->METADATATREE_DEFAULTS['5_6']['default'] = 'système scolaire';
\$CFG->METADATATREE_DEFAULTS['5_8']['default'] = 'medium';
\$CFG->METADATATREE_DEFAULTS['6_1']['default'] = 'no';
\$CFG->METADATATREE_DEFAULTS['6_2']['default'] = 'yes';
\$CFG->METADATATREE_DEFAULTS['6_3']['default'] = 'Education Nationale';
\$CFG->METADATATREE_DEFAULTS['5_12']['default'] = 'apprendre';

require_once(dirname(__FILE__) . '/lib/setup.php');

";

if (!preg_match('/vconfig\.php/s', $configfile)) {
    if (!copy($configfilelocation, $configsavefilelocation)) {
        die ("Could not make backup file of config. Aborting config fix.\n");
    }

    $configfile = str_replace($origin, $replacement, $configfile);
    
    $CONFIG = fopen($configfilelocation, 'wb');
    fputs($CONFIG, $configfile);
    fclose($CONFIG);
    echo "Moodle config file patched\n";
    return 0;
}
echo "Moodle config file unchanged. Vconfig already in place.\n";
return 0;
