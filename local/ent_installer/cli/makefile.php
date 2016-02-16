<?php

$make = array();
$checks = array();
$steps = array();

$steps[1] = "Prepare server";
$make[1] = array(
    array('Upddate packages', 'skiponfailure', 'apt-get update'),
    array('Intall PHP5 for apache', 'skiponfailure', 'apt-get install libapache2-mod-php5 -q -y'),
    array('Install ntp', 'skiponfailure', 'apt-get install ntp -q -y'),
    array('Install Curl for PHP5', 'skiponfailure', 'apt-get install php5-curl -q -y'),
    array('Instal xsl for PHP5', 'skiponfailure', 'apt-get install php5-xsl -q -y'),
    array('Install apc for PHP5', 'skiponfailure', 'apt-get install php-apc -q -y'),
    array('Install subversion', 'skiponfailure', 'apt-get install subversion -q -y'),
    array('Install mysql for PHP5', 'skiponfailure', 'apt-get install php5-mysql -q -y'),
    array('Install ldap for PHP5', 'skiponfailure', 'apt-get install php5-ldap -q -y'),
    array('Install mysql clients', 'skiponfailure', 'apt-get install mysql-client -q -y'),
    array('Install Tex as texlive', 'skiponfailure', 'apt-get install texlive -q -y'),
    array('Install aspell text checker', 'skiponfailure', 'apt-get install aspell -q -y'),
    array('Install aspell french extension', 'skiponfailure', 'apt-get install aspell-fr -q -y'),
    array('Install json for PHP5', 'skiponfailure', 'apt-get install php5-json -q -y')
);

### Checks delivery packs and internet proxy

$steps[2] = "Checks delivery packs and internet proxy";
$checks[2] = array(
    array('Check global NFS mount', 'dir', $NFSROOTMOUNT),
    // array('Check proxy settings', 'grep', "grep -eproxy.realyce.fr /etc/subversion/servers"),
    array('Check source app dir', 'dir', "{$NFSROOTMOUNT}/src"),
);
$make[2] = array(
    array('Make source app dir', 'stopformanual', " Load package files in src location and run make.php 3- to continue."),
);

# Package checks

$steps[3] = "Checks application packages";
$checks[3] = array(
    array('Check moodle toolscripts pack', 'file', $NFSROOTMOUNT.'/src/prodscripts-moodle.zip'),
    array('Check moodle template pack', 'file', $NFSROOTMOUNT.'/src/template-moodle.zip'),
    // array('Check chamilo toolscripts pack', 'file', $NFSROOTMOUNT.'/src/prodscripts-chamilo.zip'),
    // array('Check chamilo template pack', 'file', $NFSROOTMOUNT.'/src/template-chamilo{$RELEASEDOMAINPOSTFIX}.zip'),
);

$make[3] = array(
    array('application code container', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app "),
    array('shared web code container', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/www "),
    array('Moodle master execution code', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium"),
    // array('Chamilo master execution code', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium"),
    array('delivery tools', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts"),
    array('moodle delivery tools', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    // array('chamilo delivery tools', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    array('shared application data container', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data"),
    // array('shared chamilo data master container', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/chamilodata"),
    array('shared moodle data container', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/moodledata"),
);

### prepare master moodledata

$steps[4] = "Prepare master moodledata";
$make[4] = array(
    array('moodledata root for master moodle install', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/moodledata/commun"),
    // array('course root for master chamilo install', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/chamilodata/courses_master"),
    // array('archive root for master chamilo install', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/chamilodata/archive/master.{$RELEASEDOMAIN}.atrium-paca.fr"),
    // array('home root for master chamilo install', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/data/chamilodata/home/master.{$RELEASEDOMAIN}.atrium-paca.fr")
);

### prepare local containers

$steps[5] = "Prepare local containers";
$make[5] = array(
    array('local absolute app container', 'skipexists', "mkdir /app"),
    array('local absolute data container', 'skipexists', "mkdir /data"),
    array('change to local roots', 'changedir', "/app"),
    array('prepare symlink to apps', 'skipfail', "ln -s {$NFSROOTMOUNT}/app/www"),
    array('prepare symlink to delivery tools', 'skipfail', "ln -s {$NFSROOTMOUNT}/app/prodscripts"),
    array('change to local roots', 'changedir', "/data"),
    array('prepare symlink to moodledata', 'skipfail', "ln -s {$NFSROOTMOUNT}/data/moodledata"),
    // array('prepare symlink to apps', 'skipfail', "ln -s {$NFSROOTMOUNT}/data/chamilodata"),
);

### Deploying tools

$steps[6] = "Prepare local containers";
$make[6] = array(

    array('change dir to src to deploy tools' , 'changedir', "${NFSROOTMOUNT}/src"),
    array('unzip tools' , 'stoponfailure', "unzip prodscripts-moodle.zip"),
    array('install tools in delivery location', 'stoponfailure', "cp -R prodscripts-moodle/* /app/prodscripts/moodle24-ene-atrium"),
    // array('unzip tools' , 'stoponfailiure', "unzip prodscripts-chamilo.zip"),
    // array('install tools in delivery location', 'stoponfailure', "cp -R prodscripts-chamilo/* /app/prodscripts/chamilo19-ene-atrium"),
    array('make moodle delivery savedirs' , 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium/moodle24-ene-atrium-SAVE"),
    array('make moodle delivery supersavedirs', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium/moodle24-ene-atrium-SUPERSAVE"),
    // array('make chamilo delivery savedirs' , 'skippexists' => "mkdir -p ${NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium/chamilo19-ene-atrium-SAVE"),
    // array('make moodle delivery supersavedirs', 'skipexists', "mkdir -p {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium/chamilo19-ene-atrium-SUPERSAVE"),
    array('changing dir to moodle delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    array('make link to real prod code', 'stoponfailure', "ln -s {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium"),
    // array('changing dir to moodle delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    // array('make link to real prod code', 'stoponfailure', "ln -s ${NFSROOTMOUNT}/app/www/chamilo19-ene-atrium"),
);

### admin users

$steps[7] = "Add admin users";
$make[7] = array(
    array('creating service user', 'skipfail', "useradd lmsadm"),
    array('giving ownership on prod code', 'stoponfailure', "chown -R lmsadm:lmsadm {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium"),
    array('giving rights on prod code', 'stoponfailure', "chmod -R u+rwx,g+rwxs,o+rx {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium"),
    // array('giving ownership on prod code', 'stoponfailure', "chown -R lmsadm:lmsadm {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium"),
    // array('giving rights on prod code', 'stoponfailure', "chmod -R u+rwx,g+rws,o+rx {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium"),
    array('giving ownership on delivery', 'stoponfailure', "chown -R lmsadm:lmsadm {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    array('giving rights on delivery code', 'stoponfailure', "chmod -R u+rwx,g+rwxs,o+rx {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    // array('giving ownership on delivery', 'stoponfailure', "chown -R lmsadm:lmsadm {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    // array('giving rights on delivery code', 'stoponfailure', "chmod -R u+rwx,g+rwxs,o+rx {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    array('changing dir to delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    // array('changing dir to delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    array('giving rights on delivery', 'stoponfailure', "chmod ug+rwx,o+r update checkout directupdate commit syncback svntoprod supersyncback"),
    array('add apache ownership on data files', 'stoponfailure', "chown -R www-data:www-data {$NFSROOTMOUNT}/data/moodledata"),
    array('giving rights on data filesystems', 'stoponfailure', "chmod -R u+rwx,g+rws,o+rx {$NFSROOTMOUNT}/data/moodledata"),
    // array('add apache ownership on data filesystems', 'stoponfailure', "chown -R www-data:www-data {$NFSROOTMOUNT}/data/chamilodata"),
    // array('add apache rights on data filesystems', 'stoponfailure', "chmod -R u+rwx,g+rws,o+rx {$NFSROOTMOUNT}/data/chamilodata"),
);

### setting up sudo policy

# in /etc/sudoers.d/lms_sudos :

$steps[8] = "Setting up sudo policy";
$make[8] = array(
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias GOBACK = /app/prodscripts/*/goback\\n\" > /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias UPDATE = /app/prodscripts/*/update\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias SVNTOPROD = /app/prodscripts/*/svntoprod\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias SYNCBACK = /app/prodscripts/*/syncback\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias SUPERSYNCBACK = /app/prodscripts/*/supersyncback\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"Cmnd_Alias DELIVERY = GOBACK,UPDATE,SYNCBACK,SVNTOPROD,SUPERSYNCBACK\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('cat sudo file', 'stoponfailure', "echo \"www-data ALL=(lmsadm) NOPASSWD: DELIVERY\\n\" >> /etc/sudoers.d/lms_sudos"),
    array('change sudo files rights', 'stoponfailure', "chmod 0400 /etc/sudoers.d/lms_sudos"),
);

### execute delivery deployment of code

$steps[9] = "Execute delivery deployment of code";
$make[9] = array(
    array('Ensure prod is empty', 'stoponfailure', "rm -rf {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium/*"),
    array('Ensure prod has no hidden in', 'stoponfailure', "rm -rf {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium/.*"),
    array('Changing dir to delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),
    array('Checkouting code', 'stoponfailure', "./checkout"),
    array('Get svn dir for updates', 'stoponfailure', "cp -R {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium/moodle24-ene-atrium/.svn {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium"),

    // array('Ensure prod is empty', 'stoponfailure', "rm -rf {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium/*"),
    // array('Ensure prod has no hidden in', 'stoponfailure', "rm -rf {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium/.*"),
    // array('Changing dir to delivery', 'changedir', "{$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
    // array('Checkouting code', 'stoponfailure', "./checkout"),
    // array('Get svn dir for updates', 'stoponfailure', "cp -R {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium/chamilo19-ene-atrium/.svn {$NFSROOTMOUNT}/app/prodscripts/chamilo19-ene-atrium"),
);

### execute platform install

$steps[10] = "Execute platform install";
$make[10] = array(
    array('Install platform', 'stoponfailure', "sudo -u www-data php {$NFSROOTMOUNT}/app/prodscripts/moodle24-ene-atrium/moodle24-ene-atrium/admin/cli/install.php 
      --lang=fr --dataroot=/data/moodledata/commun --wwwroot={$PROTO}://commun.moodle{$RELEASEDOMAINPOSTFIX}.atrium-paca.fr --dbtype=mysqli --dbhost={$DBHOST} --dbuser=lmsadm --dbpass={$DBPASS}
      --dbname=moodle24_ene_atrium_commun --fullname='Site commun ATRIUM PACA' --shortname=ATRIUMPACA --adminuser=admin --adminpass={$FIRSTADMINPASS} --non-interactive --allow-unstable --agree-license"),
    array('Fix config file and add virtualisation', 'stoponfailure', "sudo -u lmsadm php {$NFSROOTMOUNT}/app/www/moodle24-ene-atrium/local/ent_installer/cli/fix_config.php"),
    // array('Install platform', 'stopformanual', "Chamilo needs Web interactive installation. Stopping. Please lauch prepare_server.php 9- to continue after manual step"),
);

### set postinstall ownerships and permissions

$steps[11] = "Set postinstall ownerships and permissions";
$make[11] = array(
    array('Give ownership on moodle delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/prodscripts/moodle24-ene-atrium "),
    array('Give group permissions on moodle delivery', 'stoponfailure', "chmod -R g+rwxs /app/prodscripts/moodle24-ene-atrium "),
    array('Give ownership on moodle prod', 'stoponfailure', "chown -R lmsadm:lmsadm /app/www/moodle24-ene-atrium "),
    array('Give group permissions on moodle prod', 'stoponfailure', "chmod -R g+rwxs /app/www/moodle24-ene-atrium "),

    // array('Fix config file and add virtualisation', 'stoponfailure', "sudo -u lmsadm php {$NFSROOTMOUNT}/app/www/chamilo19-ene-atrium/local/ent_installer/cli/fix_config.php"),
    // array('Give ownership on chamilo delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chmod -R ugo+r,ug+w /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chmod -R g+rwxs /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/www/chamilo19-ene-atrium"),
    // array('Give ownership on vchamilo templates', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/plugin/vchamilo/templates"),
    // array('Give ownership on chamilo courses', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/courses"),
    // array('Give ownership on chamilo archives', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/archive"),
    // array('Give ownership on chamilo archives', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/main/upload/users"),
    // array('Give ownership on chamilo home', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/home"),
);

### Check install of master platforms

$steps[12] = "Check install of master platforms";
$make[12] = array(
    array('Check main platform is running', 'stopformanual', "At this step you need check master Moodle is running. Stopping. Please lauch prepare_server.php 11- to continue after manual step"),
    // array('Check main platform is running', 'stopformanual', "At this step you need check master Chamilo is running. Stopping. Please lauch prepare_server.php 11- to continue after manual step"),
);

### Give permissions on installed files

$steps[13] = "Give permissions on installed files";
$make[13] = array(
    array('Give ownership on moodle delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/prodscripts/moodle24-ene-atrium "),
    array('Give group permissions on moodle delivery', 'stoponfailure', "chmod -R g+rwxs /app/prodscripts/moodle24-ene-atrium "),
    array('Give ownership on moodle prod', 'stoponfailure', "chown -R lmsadm:lmsadm /app/www/moodle24-ene-atrium "),
    array('Give group permissions on moodle prod', 'stoponfailure', "chmod -R g+rwxs /app/www/moodle24-ene-atrium "),

    // array('Give ownership on chamilo delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chmod -R ugo+r,ug+w /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chmod -R g+rwxs /app/prodscripts/chamilo19-ene-atrium"),
    // array('Give permissions on chamilo delivery', 'stoponfailure', "chown -R lmsadm:lmsadm /app/www/chamilo19-ene-atrium"),
    // array('Give ownership on vchamilo templates', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/plugin/vchamilo/templates"),
    // array('Give ownership on chamilo courses', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/courses"),
    // array('Give ownership on chamilo archives', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/archive"),
    // array('Give ownership on chamilo archives', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/main/upload/users"),
    // array('Give ownership on chamilo home', 'stoponfailure', "chown -R lmsadm:www-data /app/www/chamilo19-ene-atrium/home"),
);

### Deploy templates

$steps[14] = "Deploy templates";
$make[14] = array(
    array('Change dir to src to deploy tools' , 'changedir', "${NFSROOTMOUNT}/src"),
    array('Unzip template' , 'stoponfailure', "unzip template-moodle.zip"),
    array('Install template in template location', 'stoponfailure', "cp -R template-moodle/* /data/moodledata/commun/vmoodle"),
    array('Copy nodelist into production location', 'stoponfailure', "cp vmoodle-nodelist{$RELEASEDOMAINPOSTFIX}.csv/* /app/www/moodle24-ene-atrium/blocks/vmoodle/cli"),
    // array('Unzip templates' , 'stoponfailiure' => "unzip template-chamilo{$RELEASEDOMAINPOSTFIX}.zip"),
    // array('Install tools in delivery location', 'stoponfailure', "cp -R template-chamilo{$RELEASEDOMAINPOSTFIX}/* /app/www/chamilo19-ene-atrium/plugin/vchamilo/templates"),
    // array('Copy nodelist into production location', 'stoponfailure', "cp vchamilo-nodelist{$RELEASEDOMAINPOSTFIX}.csv/* /app/www/chamilo19-ene-atrium/plugin/vchamilo/cli"),
);

### Make subplatforms

$steps[15] = "Make subplatforms";
$make[15] = array(
    array('change dir to vmoodle cli' , 'changedir', "/app/www/moodle24-ene-atrium/blocks/vmoodle/cli"),
    array('launch instance maker' , 'stoponfailure', " sudo -u www-data php bulkcreatenodes.php --nodes=vmoodle-nodelist{$RELEASEDOMAINPOSTFIX}.csv "),
    // array('change dir to vmoodle cli' , 'changedir', "/app/www/chamilo19-ene-atrium/plugin/vchamilo/cli"),
    // array('launch instance maker' , 'stoponfailure', " sudo -u www-data php bulkcreatenodes.php --nodes=vchamilo-nodelist{$RELEASEDOMAINPOSTFIX}.csv "),
);

### Feed platforms accounts

$steps[16] = "Feed platforms accounts";
$make[16] = array(
    array('Change dir to ent_installer cli' , 'changedir', "/app/www/moodle24-ene-atrium/local/ent_installer/cli"),
    array('Launch accounts feeder' , 'stoponfailure', " sudo -u www-data php sync_hosts.php --logroot=/data/log/moodle --distributed"),
    // array('Change dir to ent_installer cli' , 'changedir', "/app/www/chamilo19-ene-atrium/local/ent_installer/cli"),
    // array('Launch accounts feeder' , 'stoponfailure', " sudo -u www-data php sync_hosts.php --logroot=/data/log/chamilo"),
);

### Setup crons

$steps[17] = "Setup crons";
$make[17] = array(
    array('Setup crons on technical service server' , 'stopformanual', " crons need to be setup on a separate service server. See DSD "),
);
