<?php

/**  -*- coding: utf-8 -*-
 *
 * Copyright 2022, dpa-IT Services GmbH
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class AdminPagePlus
{
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_options_page'));
        add_action("admin_init", array($this, "add_options"));
    }

    public function add_options_page()
    {
        add_submenu_page(
            'options-general.php',
            'dpa-digitalwiresplus-Import',
            'dpa-digitalwiresplus-Import',
            'manage_options',
            'dpa-digitalwiresplus',
            array(&$this, 'admin_page_html')
        );
    }

    public function add_options()
    {
        $cur_settings = get_option("dpa-digitalwiresplus");

        add_settings_section(
            "dpa-digitalwiresplus-section",
            "dpa-digitalwiresplus-Import",
            array(&$this, "admin_page_description"),
            "dpa-digitalwiresplus"
        );

        add_settings_field(
            "dpa-digitalwiresplus[dwp_endpoint]",
            "Endpunkt(e) (getrennt durch Zeilenumbruch)",
            array(&$this, "form_endpoint_html"),
            "dpa-digitalwiresplus",
            "dpa-digitalwiresplus-section",
            $cur_settings["dwp_endpoint"]
        );

        add_settings_field(
            "dpa-digitalwiresplus[dwp_cron_time]",
            "Abfragezyklus in Minuten",
            array(&$this, "form_cron_time_html"),
            "dpa-digitalwiresplus",
            "dpa-digitalwiresplus-section",
            $cur_settings["dwp_cron_time"]
        );

        add_settings_field(
            "dpa-digitalwiresplus[dwp_active]",
            "Aktiviert",
            array(&$this, "form_active_html"),
            "dpa-digitalwiresplus",
            "dpa-digitalwiresplus-section",
            $cur_settings["dwp_active"]
        );
    }

    public function admin_page_description()
    {
        echo '<p>Registrieren Sie sich zunächst am <a href="https://api-portal.dpa-newslab.com/" target="_blank">dpa-API-Portal</a>, um einen eigenen wireQ-API-Endpunkt freizuschalten und ihre Wordpress-Instanz mit Inhalte beliefern zu lassen</p>';
    }

    public function form_endpoint_html($cur_val)
    {
        echo '<textarea name="dpa[dpa_endpoint]" id="dpa_endpoint" rows="5" cols="50">' . $cur_val . '</textarea>';
    }

    public function form_cron_time_html($cur_val)
    {
        if (empty($cur_val)) {
            $cur_val = 5;
        }
        echo '<input type="number" min="3" max="60" name="dpa-digitalwiresplus[dwp_cron_time]" id="dwp_cron_time" value="' . $cur_val . '">';
    }

    public function form_active_html($cur_val)
    {
        echo '<input type="checkbox" name="dpa-digitalwiresplus[dwp_active]" id="dwp_active" ' . checked(true, $cur_val, false) . ' />';
    }

    public function admin_page_html()
    {
        // check user capabilities
        $dwp_stats = get_option('dwp_stats')
?>
        <div class="wrap">
            <form method="post" action="options.php">
                <?php settings_fields("dpa-digitalwiresplus") ?>
                <?php do_settings_sections('dpa-digitalwiresplus') ?>
                <?php submit_button(); ?>
            </form>
            <code style="display: block;width: fit-content;">
                Letzte Abfrage: <?php echo $dwp_stats['last_run']; ?>
                <?php if (isset($dwp_stats['last_import_title'])) { ?>
                    <br><br>
                    Letzter importierter Artikel: <?php echo $dwp_stats['last_import_title'] ?> (<?php echo $dwp_stats['last_import_urn'] ?>, <?php echo $dwp_stats['last_import_timestamp'] ?>)
                <?php } ?>
                <?php if (isset($dwp_stats['last_exception_message'])) { ?>
                    <br><br>
                    Letzter Import-Fehler: <?php echo $dwp_stats['last_exception_message'] ?> (aufgetreten bei <?php echo $dwp_stats['last_exception_urn'] ?>, <?php echo $dwp_stats['last_exception_timestamp'] ?>)
                <?php } ?>
            </code>
        </div>
<?php
    }
}


?>