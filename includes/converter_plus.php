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

class ConverterPlus
{
    public function __construct()
    {
        $this->register_meta_fields();
    }

    private function register_meta_fields()
    {
        register_post_meta('post', 'dwp_urn', [
            'type' => 'string',
            'description' => 'digitalwiresplus urn',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('post', 'dwp_version', [
            'type' => 'integer',
            'description' => 'digitalwiresplus version',
            'single' => true,
            'show_in_rest' => true
        ]);
        register_post_meta('post', 'dwp_version_created', [
            'type' => 'string',
            'description' => 'digitalwiresplus version_created',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('post', 'dwp_updated', [
            'type' => 'string',
            'description' => 'digitalwiresplus updated',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('attachment', 'dwp_parent_urn', [
            'type' => 'string',
            'description' => 'digitalwiresplus urn',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('attachment', 'dwp_urn', [
            'type' => 'string',
            'description' => 'digitalwiresplus urn',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('attachment', 'dwp_version', [
            'type' => 'integer',
            'description' => 'digitalwiresplus version',
            'single' => true,
            'show_in_rest' => true
        ]);

        register_post_meta('attachment', 'dwp_version_created', [
            'type' => 'string',
            'description' => 'digitalwiresplus version_created',
            'single' => true,
            'show_in_rest' => true
        ]);
    }

    private function get_post_by_meta($key, $value)
    {
        $query = get_posts([
            'meta_query' => [
                [
                    'key' => $key,
                    'compare' => '=',
                    'value' => $value
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);

        if (empty($query)) {
            error_log('No existing post found');
            return NULL;
        } else {
            error_log('Existing post found');
            return $query[0];
        }
    }

    private function get_attachment_by_meta($key, $value)
    {
        $query = get_posts([
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_query' => [
                [
                    'key' => $key,
                    'compare' => '=',
                    'value' => $value
                ]
            ],
            'fields' => 'ids',
            'posts_per_page' => 1
        ]);

        if (empty($query)) {
            return NULL;
        } else {
            return $query[0];
        }
    }

    public function add_association($a, $parent_urn)
    {
        $existing_attachment_id = $this->get_attachment_by_meta('dwp_urn', $a['urn']);

        $attachment_data = [
            'post_mime_type' => $a['renditions'][0]['mimetype'],
            'post_title' => $a['headline'],
            'post_content' => $a['caption'],
            'post_excerpt' => $a['caption'] . ' Foto: ' . $a['creditline'],
            'post_status' => 'inherit',
            'post_date_gmt' => date('Y-m-d H:i:s', strtotime($a['version_created'])),
            'meta_input' => array(
                '_wp_attachment_image_alt' => $a['caption'],
                'dwp_parent_urn' => $parent_urn,
                'dwp_urn' => $a['urn'],
                'dwp_version' => $a['version'],
                'dwp_version_created' => $a['version_created']
            )
        ];

        if ($existing_attachment_id != NULL) {
            $attachment_meta = get_post_meta($existing_attachment_id);

            if (
                strcmp($a['version'], $attachment_meta['dwp_version'][0]) <= 0 &&
                strcmp($a['version_created'], $attachment_meta['dwp_version_created'][0]) < 0
            ) {
                $path = parse_url($a['renditions'][0]['url'], PHP_URL_PATH);
                $filename = basename($path);

                $filedata = file_get_contents($a['renditions'][0]['url']);

                if ($filedata === False) return False;

                $upload_file = wp_upload_bits($filename, null, $filedata);

                update_attached_file($existing_attachment_id, $upload_file['url']);
                $attachment_metadata = wp_generate_attachment_metadata($existing_attachment_id, $upload_file['url']);
                wp_update_attachment_metadata($existing_attachment_id, $attachment_metadata);
                wp_update_post($existing_attachment_id, $attachment_data);

                error_log('Attachment ' . $a['urn'] . ' (v' . $a['version'] . ' updated, id ' . $existing_attachment_id . ')');
            } else {
                error_log('Attachment ' . $a['urn'] . ' (v' . $a['version'] . ') already saved (id ' . $existing_attachment_id . ').Skipping');
            }

            return $existing_attachment_id;
        } else {
            $path = parse_url($a['renditions'][0]['url'], PHP_URL_PATH);
            $filename = basename($path);

            $filedata = file_get_contents($a['renditions'][0]['url']);

            if ($filedata === False) return False;

            $upload_file = wp_upload_bits($filename, null, $filedata);

            $attachment_id = wp_insert_attachment($attachment_data, $upload_file['url']);

            if (!is_wp_error($attachment_id)) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $upload_file['url']);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                error_log('Attachment ' . $a['urn'] . ' (v' . $a['version'] . ') added as id ' . $attachment_id);
                return $attachment_id;
            }
        }
    }

    private function clean_html($html, $dateline)
    {
        $input_dom = new DOMDocument();
        $output_dom = new DOMDocument();

        //Prevent parsing errors on valid HTML5-elements, e.g. <section>
        libxml_use_internal_errors(true);
        $input_dom->loadHTML('<?xml encoding=\"utf-8\" ?>' . $html);
        libxml_clear_errors();

        $xpath = new DOMXpath($input_dom);

        //Insert dateline
        if (!empty($dateline)) {
            $dateline = new DOMText($dateline);
            $firstP = $xpath->evaluate('//section/p[1]')[0];
            $firstP->insertBefore($dateline, $firstP->firstChild);
        }

        foreach ($xpath->evaluate('//section/*') as $article_part) {
            $node = $output_dom->importNode($article_part, true);
            $output_dom->appendChild($node);
        }

        return $output_dom->saveHTML();
    }

    private function get_tags($categories)
    {
        return array_map(function ($v) {
            return $v['name'];
        }, array_filter($categories, function ($v) {
            return in_array($v['type'], array('dnltype:dpasubject', 'dnltype:keyword', 'dnltype:geosubject'));
        }));
    }

    protected function post_process_post($dwp_entry, $post)
    {
        //Function to add custom post configurations. Keep data previously added to meta_input (dwp_urn, dwp_version,...) to ensure updates will still work
        return $post;
    }

    public function add_post($dwp_entry)
    {
        $post = array(
            'post_title' => $dwp_entry['headline'],
            'post_excerpt' => isset($dwp_entry['teaser']) ? $dwp_entry['teaser'] : '',
            'post_content' => $this->clean_html($dwp_entry['article_html'], $dwp_entry['dateline']),
            'post_status' => 'publish',
            'post_date_gmt' => date('Y-m-d H:i:s', strtotime($dwp_entry['version_created'])),
            'guid' => $dwp_entry['urn'],
            'tags_input' => $this->get_tags($dwp_entry['categories']),
            'meta_input' => array(
                'dwp_urn' => $dwp_entry['urn'],
                'dwp_version' => $dwp_entry['version'],
                'dwp_version_created' => $dwp_entry['version_created'],
                'dwp_updated' => $dwp_entry['updated']
            )
        );

        $post = $this->post_process_post($dwp_entry, $post);

        $existing_post_id = $this->get_post_by_meta('dwp_urn', $dwp_entry['urn']);
        if ($existing_post_id != NULL) {
            $post['ID'] = $existing_post_id;

            $post_meta = get_post_meta($post['ID']);

            if (
                $dwp_entry['version'] >= $post_meta['dwp_version'][0] &&
                strcmp($dwp_entry['version_created'], $post_meta['dwp_version_created'][0]) >= 0 &&
                strcmp($dwp_entry['updated'], $post_meta['dwp_updated'][0]) > 0
            ) {
                error_log('Updating post with id ' . $post['ID']);

                $associations = array();

                foreach ($dwp_entry['associations'] as &$a) {
                    $association_id = $this->add_association($a, $dwp_entry['urn']);

                    if ($association_id !== False) {
                        array_push($associations, $association_id);

                        if ($a['is_featureimage']) {
                            set_post_thumbnail($post['ID'], $association_id);
                        }
                    } else {
                        error_log('Importing association ' . $dwp_entry['urn'] . ' failed.');
                    }
                }

                $resp = wp_update_post($post);
                if ($resp === 0 | is_wp_error($resp)) {
                    throw new Exception('Updating post ' . $post['ID'] . ' failed.');
                } else {
                    error_log('Updating post ' . $post['ID'] . ' done.');
                }
            } else {
                error_log('No updates to previous version. Skipping');
            }
        } else {
            error_log('Inserting post');
            $associations = array();
            $feature_image_id;

            foreach ($dwp_entry['associations'] as &$a) {
                $association_id = $this->add_association($a, $dwp_entry['urn']);
                if ($association_id !== False) {
                    array_push($associations, $association_id);

                    if ($a['is_featureimage']) {
                        $feature_image_id = $association_id;
                    }
                } else {
                    error_log('Importing association ' . $dwp_entry['urn'] . ' failed.');
                }
            }

            $resp = wp_insert_post($post);
            if ($resp === 0 | is_wp_error($resp)) {
                throw new Exception('Inserting post for urn ' . $dwp_entry['urn'] . ' failed.');
            } else {
                if (!empty($feature_image_id)) {
                    set_post_thumbnail($resp, $feature_image_id);
                }
                error_log('Inserting post with new id ' . $resp . ' done.');
            }
        }
    }

    public function remove_post($entry)
    {
        $existing_post_id = $this->get_post_by_meta('dwp_urn', $dwp_entry['urn']);

        error_log('Deleting post ' . $existing_post_id . ' for urn ' . $dwp_entry['urn']);

        wp_trash_post($existing_post_id);

        error_log('Deleting post ' . $existing_post_id . ' done.');
    }
}
