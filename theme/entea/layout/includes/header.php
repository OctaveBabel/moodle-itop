<?php
// Without the "message manager" plugin you can put your own here (caches will automatically be renewed)
/*
<div class="administratormessage" style="padding:2px 2px 2px 10px;font-size:10px;background-color:#FD6060">
<b>Message de service global</b> (toutes plates-formes) : 
// Put your message here
</div>
*/
?>
<header role="banner" class="navbar navbar-fixed-top<?php echo $html->navbarclass ?> moodle-has-zindex">
    
    <div class="header-banner row-fluid">
    <div class="header-banner-left span8" ></div>
    <div class="header-banner-right "></div>
    </div>
    <nav role="navigation" class="navbar-inner ">
        <div class="container-fluid">
            <a class="brand" href="<?php echo $CFG->wwwroot;?>"><?php echo $SITE->fullname; ?></a>
            <a class="btn btn-navbar" data-toggle="workaround-collapse" data-target=".nav-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a>

            <?php echo $OUTPUT->user_menu(); ?>
            <div class="nav-collapse collapse">
                <?php echo $OUTPUT->custom_menu(); ?>
                <ul class="nav pull-right">
                    <li><?php echo $OUTPUT->page_heading_menu(); ?></li>
                </ul>
            </div>
            
        </div>
      
    </nav>
</header>