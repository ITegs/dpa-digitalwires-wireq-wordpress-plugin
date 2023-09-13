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

 * Plugin Name: dpa-digitalwiresplus-to-wordpress
 * Description: Import multiple dpa-articles using the wireQ-api
 * Version: 1.1.0
 * Requires at least: 5.0
 */

//If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

define('PLUGIN_NAME_VERSION', '1.1.0');

if (!class_exists('DpaDigitalwiresPlus_Plugin')) {
    class DpaDigitalwiresPlus_Plugin
    {
        private $admin_page;
        private $api;
        private $converter;

        public function __construct()
        {
            add_action('init', array($this, 'setup'));

            register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        }

        public function setup()
        {
            $this->load_dependencies();
            $this->register_settings();

            add_action('update_option_dpa-digitalwiresplus', array($this, 'update_settings'));
            add_filter('cron_schedules', array($this, 'dwp_schedule'));
            add_action('dpa_digitalwiresplus_cron', array($this, 'import_articles'));
        }

        public function deactivate()
        {
            $next_dwp_task = wp_next_scheduled('dpa_digitalwiresplus_cron');
            wp_unschedule_event($next_dwp_task, 'dpa_digitalwiresplus_cron');

            unregister_setting('dpa-digitalwiresplus', 'dpa-digitalwiresplus');
            delete_option('dpa-digitalwiresplus');
            delete_option('dwp_stats');

            error_log('dpa-digitalwiresplus-Plugin deactivated');
        }

        public function dwp_schedule($schedules)
        {
            $config = get_option('dpa-digitalwiresplus');

            $schedules['digitalwiresplus-schedule'] = array(
                'interval' => $config['dwp_cron_time'] * 60,
                'display' => 'Every ' . $config['dwp_cron_time'] . ' minutes'
            );

            return $schedules;
        }

        public function update_settings()
        {
            error_log('dpa-digitalwiresplus-Plugin settings updated');

            $config = get_option('dpa-digitalwiresplus');

            $next_dwp_task = wp_next_scheduled('dpa_digitalwiresplus_cron');
            if ($config['dwp_active'] === true) {
                $this->api = new digitalwiresplusAPI($config['dwp_endpoint']);

                if ($next_dwp_task) {
                    wp_schedule_event($next_dwp_task, 'digitalwiresplus-schedule', 'dpa_digitalwiresplus_cron');
                    error_log('Rescheduled dpa-digitalwiresplus-wireQ-cron');
                } else {
                    wp_schedule_event(time(), 'digitalwiresplus-schedule', 'dpa_digitalwiresplus_cron');
                    error_log('Added dpa-digitalwiresplus-wireQ-cron');
                }
            } elseif ($next_dwp_task) {
                error_log('Removed dpa-digitalwiresplus-wireQ-cron');
                wp_unschedule_event($next_dwp_task, 'dpa_digitalwiresplus_cron');
            }
        }

        private function register_settings()
        {
            register_setting('dpa-digitalwiresplus', 'dpa-digitalwiresplus', array(
                'default' => array(
                    'dwp_endpoint' => null,
                    'dwp_cron_time' => 5,
                    'dwp_active' => false,
                ),
                'sanitize_callback' => array($this, 'validate_input')
            ));

            add_option('dwp_stats', array(
                'last_run' => '-',
                'last_import_title' => null,
                'last_import_urn' => null,
                'last_import_timestamp' => null,
                'last_exception_message' => null,
                'last_exception_urn' => null,
                'last_exception_timestamp' => null
            ));
        }

        public function validate_input($input)
        {
            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce($_POST['_wpnonce'], 'dpa-digitalwiresplus-options')
            ) {
                add_settings_error('dpa-digitalwiresplus', 'invalid_nonce', 'Formular-Validierung fehlgeschlagen', 'error');
            }

            $current_config = get_option('dpa-digitalwiresplus');
            $output = array(
                'dwp_endpoint' => $this->validate_endpoint($input['dwp_endpoint'], $current_config['dwp_endpoint']),
                'dwp_cron_time' => $this->validate_cron_time($input['dwp_cron_time'], $current_config['dwp_cron_time'])
            );

            if (!empty($input['dwp_active']) && !empty($output['dwp_endpoint'])) {
                $output['dwp_active'] = $this->validate_active($input['dwp_active']);
            } else {
                $output['dwp_active'] = false;
            }

            return apply_filters('dpa-digitalwiresplus', $output, $input);
        }

        private function validate_cron_time($input, $old)
        {
            if (empty($input)) {
                add_settings_error('dpa-digitalwiresplus', 'invalid_time', 'Abfragezyklus fehlt', 'error');
                return $old;
            }

            return $input;
        }

        private function validate_endpoint($input, $old)
        {
            $endpoint;
            $valid = true;

            if (!empty($input) && substr($input, 0, 37) != 'https://digitalwires.dpa-newslab.com/') {
                $valid = false;
                add_settings_error('dpa-digitalwiresplus', 'invalid_url', 'URL ist kein bekannter dpa-digitalwires-Endpunkt', 'error');
                return $old;
            } else {
                return esc_attr($input);
            }
        }

        private function validate_active($input)
        {
            return apply_filters('dwp_active', $input === 'on', $input);
        }

        private function load_dependencies()
        {
            require_once plugin_dir_path(__FILE__) . 'includes/api_plus.php';

            $digitalwiresplus_option = get_option('dpa-digitalwiresplus');
            if (isset($digitalwiresplus_option['dwp_endpoint'])) {
                $this->api = new digitalwiresplusAPI($digitalwiresplus_option['dwp_endpoint']);
            }

            require_once plugin_dir_path(__FILE__) . '/includes/admin_plus.php';
            $this->admin_page = new AdminPage();

            require_once plugin_dir_path(__FILE__) . '/includes/converter_plus.php';
            $this->converter = new ConverterPlus();
        }

        public function import_articles()
        {
            error_log('Fetching articles');

            $fetch_num = 0;
            $entries;

            $dwp_stats = get_option("dwp_stats");

            do {
                $fetch_num = $fetch_num + 1;
                $entries = ($this->api)->fetch_articles();

                foreach ($entries as $entry) {
                    try {
                        switch ($entry['pubstatus']) {
                            case 'usable':
                                $this->converter->add_post($entry);
                                break;
                            case 'canceled':
                                $this->converter->remove_post($entry);
                                break;
                        }

                        $dwp_stats['last_import_title'] = $entry['headline'];
                        $dwp_stats['last_import_urn'] = $entry['urn'];
                        $dwp_stats['last_import_timestamp'] = $entry['version_created'];
                    } catch (Exception $e) {
                        error_log($e);
                        $dwp_stats['last_exception_message'] = $e->getMessage();
                        $dwp_stats['last_exception_urn'] = $entry['urn'];
                        $dwp_stats['last_exception_timestamp'] = date("d.m.Y, H:i:s T", strtotime($entry['version_created']));
                    }
                    $this->api->remove_from_queue($entry['_wireq_receipt']);
                }
            } while (!empty($entries));

            error_log('Called API ' . print_r($fetch_num, TRUE) . ' times');

            $tz = date_default_timezone_get();
            date_default_timezone_set(get_option('timezone_string'));

            $dwp_stats['last_run'] = date('d.m.Y, H:i:s T');

            $dwp_stats['last_import_timestamp'] = date("d.m.Y, H:i:s T", strtotime($dwp_stats['last_import_timestamp']));

            date_default_timezone_set($tz);
            update_option('dwp_stats', $dwp_stats);
        }
    }

    $plugin = new Dpadigitalwiresplus_Plugin();
}
