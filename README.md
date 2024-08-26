# Site Reviews Lector

This very simple Wordpress plugin adds a proofreading function using Chat GPT for received reviews.

This version was developed for and tested with reviews from the popular Wordpress plugin [Site Reviews](https://github.com/pryley/site-reviews) by [Paul Ryley](https://github.com/pryley). The plugin is configured to not automatically publish incoming reviews.

To install this mini plugin, insert your Chat GPT API key in line 103 of the code and simply copy the updated PHP file into a new directory named "site-reviews-lector" under "wp-content/plugins".

By default, a timeout of 20 seconds is set between each proofreading session to allow new Chat GPT API users without TIER classification to adhere to the limit of three requests per minute. You can modify this value in line 191.

To start the proofreading, click on "Site Reviews Lector" under the "Settings" tab in the Wordpress admin menu.

The plugin starts directly with the first proofreading and proceeds as follows:

(Line 89) Querying the latest unedited review:

```
$query = "SELECT * FROM wp_posts WHERE post_author = 0 AND post_status = 'pending' AND post_type = 'site-review' ORDER BY post_date desc LIMIT 1";
```

After editing, the author ID is set to 1. (Adjust this if necessary in the code.)

(Line 101) Preparing the prompt and making a request to the Chat GPT API:

```
$prompt = 'Please proofread the following review: Title: ' . $org_title . ' / Review Text: ' . $org_content;

[...]

$response = json_decode(getAPIResponse($prompt, $endpoint, $api_key));
```

(Line 110) Marking the revision in the revised review text:

```
if ($changes == true) {
    $new_content .= "\n\n(edited by ChatGPT)";
}
```

Processing the reviews in loops:

- (Line 176) If a detailed customer request is detected, only the author ID is updated. The title and text of the review remain unchanged. These reviews can still be found under Site Reviews > Unapproved. From there, you can manually edit them as usual and then potentially approve them.

```
[YOUR_WEBSITE]/wp-admin/edit.php?post_status=pending&post_type=site-review&orderby=date&order=desc
```

- (Line 164) If the review is classified as not publishable, it will still be proofread and then deleted with changes. You can find these reviews under Site Reviews > Trash. From there, you can either permanently delete them or potentially restore and approve them.

```
[YOUR_WEBSITE]/wp-admin/edit.php?post_status=trash&post_type=site-review
```

- (Line 145) If no changes have been made to the text of the review, the review will be published unchanged.

- (Line 143) If changes have been made, the revised review will be published.