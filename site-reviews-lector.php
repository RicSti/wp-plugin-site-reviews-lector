<?php

/**
 * Plugin Name: Site Reviews Lector
 * Plugin URI: 
 * Description: Proofreads and approves reviews using Chat GPT.
 * Version: 0.1
 * Author: RicSti
 * Author URI: https://github.com/RicSti
 **/

class Site_Reviews_Lector
{

    public function __construct()
    {
        add_action('admin_menu', array($this, 'plugin_settings_menu_page'));
        add_action('admin_init', array($this, 'plugin_register_settings'));
    }

    public function plugin_settings_menu_page()
    {
        add_options_page(
            __('Site Reviews Lector', 'oop-menu-item-sub'),
            __('Site Reviews Lector', 'oop-menu-item-sub'),
            'manage_options',
            'site-reviews-lector',
            array($this, 'plugin_settings_page_content')
        );
    }

    public function plugin_settings_page_content()
    {
    ?>
        <div>
            <h1>Site Reviews Lector</h1>
            <?php

            function getAPIResponse($prompt, $endpoint, $api_key)
            {
                $ch = curl_init($endpoint);

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Authorization: Bearer $api_key",
                    "Content-Type: application/json"
                ));

                $data = array(
                    'model' => 'gpt-4o-mini',
                    'messages' => array(
                        array(
                            'role' => 'system',
                            'content' => 'You are an editor of a customer service hotline review platform and you are tasked with proofreading user reviews. Maintain the original wording. Remove any insults and names of individuals, as well as specific details about incidents and documents. Mark the proofread sections. Indicate whether it is a very extensive service request, but only if the user uses phrases like "call me back" or "reply to me". Decide whether the fully proofread review can be published or if there is too little readable text left after removing unwanted characters. Also, indicate whether you have made any changes to the text. Please output the result as a JSON object without any additional characters, following this schema: { "title" : "new title", "content" : "new content", "changes" : true|false, "publishable" : true|false, "service_request" : true|false }.'
                        ),
                        array('role' => 'user', 'content' => $prompt)
                    )
                );
                $data_string = json_encode($data);

                curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);

                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    echo 'Curl-Error: ' . curl_error($ch);
                    exit;
                }

                $response_data = json_decode($response, true);

                // Check if the key 'choices' is present in the response and if the message content is present
                if (!isset($response_data['choices']) || !isset($response_data['choices'][0]['message']['content'])) {
                    echo "Error: Unexpected API response.<br>";
                    echo "Complete response:<br>";
                    print_r($response_data);
                    exit;
                }

                $answer = $response_data['choices'][0]['message']['content'];

                curl_close($ch);

                return $answer;
            }

            $wpdb = $GLOBALS['wpdb'];
            $query = "SELECT * FROM wp_posts WHERE post_author = 0 AND post_status = 'pending' AND post_type = 'site-review' ORDER BY post_date desc LIMIT 1";
            $results = $wpdb->get_results($query);

            $org_post_id = $results[0]->ID;
            $org_title = $results[0]->post_title;
            $org_content = $results[0]->post_content;

            echo "<h2>Original Title</h2>";
            echo $org_title;
            echo "<h2>Original Review Text</h2>";
            echo $org_content;

            $prompt = 'Please proofread the following review: Title: ' . $org_title . ' / Review Text: ' . $org_content;

            $api_key = '[YOUR_CHAT_GPT_API_KEY]';
            $endpoint = "https://api.openai.com/v1/chat/completions";
            $response = json_decode(getAPIResponse($prompt, $endpoint, $api_key));
            
            $new_title = $response->title;
            $new_content = $response->content;
            $changes = $response->changes;
            if ($changes == true) {
                $new_content .= "\n\n(edited by ChatGPT)";
            }
            $publishable = $response->publishable;
            $service_request = $response->service_request;

            echo "<h2>New Title</h2>";
            echo $new_title;
            echo "<h2>New Review Text</h2>";
            echo $new_content;
            echo "<h2>Changes Made</h2>";
            if ($changes == true) {
                echo "Yes";
            } else {
                echo "No";
            }
            echo "<h2>Publishable</h2>";
            if ($publishable == true) {
                echo "Yes";
            } else {
                echo "No";
            }
            echo "<h2>Service Request</h2>";
            if ($service_request == true) {
                echo "Yes";
            } else {
                echo "No";
            }

            if ($service_request == false) {

                if ($publishable == true) {

                    if ($changes == true) {
                        wp_update_post(array(
                            'ID' => $org_post_id,
                            'post_author' => 1,
                            'post_title' => $new_title,
                            'post_content' => $new_content,
                            'post_status' => 'publish'
                        ));

                        echo "<h2>The review has been proofread and published.</h2>";
        
                    } else {
                        wp_update_post(array(
                            'ID' => $org_post_id,
                            'post_author' => 1,
                            'post_status' => 'publish'
                        ));

                        echo "<h2>The review has been published without any changes.</h2>";
                    }

                } else {
                    wp_update_post(array(
                        'ID' => $org_post_id,
                        'post_author' => 1,
                        'post_title' => $new_title,
                        'post_content' => $new_content,
                        'post_status' => 'trash'
                    ));

                    echo "<h2>The review has been proofread and then deleted.</h2>";
                }

            } else {

                wp_update_post(array(
                    'ID' => $org_post_id,
                    'post_author' => 1,
                    'post_status' => 'pending'
                ));

                echo "<h2>The review has been marked as a service request and returned for further processing.</h2>";
            }

            ?>
            <script>
                var meta_refresh = document.createElement('meta');
                meta_refresh.httpEquiv = 'refresh';
                meta_refresh.content = '20;url=<?php echo admin_url('options-general.php?page=site-reviews-lector'); ?>';
                document.getElementsByTagName('head')[0].appendChild(meta_refresh);
            </script>
        </div>

    <?php
    }

    public function plugin_register_settings()
    {
        register_setting('option_group', 'option_name');
        add_settings_section(
            'section_id',
            __('Settings Page Title', 'oop-menu-item-sub'),
            array($this, 'render_section'),
            'options_page'
        );
        add_settings_field(
            'html_element',
            __('Choose HTML Element:', 'oop-menu-item-sub'),
            array($this, 'render_field'),
            'options_page',
            'section_id'
        );
    }
}

$site_reviews_lector = new Site_Reviews_Lector();