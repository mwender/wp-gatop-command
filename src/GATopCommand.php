<?php
namespace MWender\GATopCommand;

use WP_CLI;

class GATopCommand {
    /**
     * Configure the service account JSON file path.
     *
     * ## EXAMPLES
     *
     *     wp gatop --configure
     *
     */
    public function configure() {
        $ABSPATH = ABSPATH;

        WP_CLI::log("Current ABSPATH is: {$ABSPATH}");

        $key_file_path = $this->prompt_user(
            'Enter the path to your service account JSON file (relative to ABSPATH)'
        );

        // Store the path in the WordPress options table.
        update_option('gatop_service_account_path', $key_file_path);

        WP_CLI::success("Configuration saved! Service account path is set to: {$key_file_path}");
    }

    /**
     * Fetch top 5 posts by views from Google Analytics.
     *
     * ## OPTIONS
     *
     * [--view-id=<view_id>]
     * : The Google Analytics View ID.
     *
     * ## EXAMPLES
     *
     *     wp gatop fetch --view-id=YOUR_VIEW_ID
     *
     * @param array $args Command-line arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function fetch($args, $assoc_args) {
        $view_id = $assoc_args['view-id'] ?? null;

        if (!$view_id) {
            WP_CLI::error('View ID is required. Use --view-id=<VIEW_ID>');
        }

        // Retrieve the key file path from the WordPress options table.
        $key_file_path = get_option('gatop_service_account_path');

        if (!$key_file_path) {
            WP_CLI::error('Service account path is not configured. Run "wp gatop --configure" to set it up.');
        }

        // Build the absolute path to the key file.
        $key_file_path = ABSPATH . $key_file_path;

        if (!file_exists($key_file_path)) {
            WP_CLI::error('Service account key file not found at: ' . $key_file_path);
        }

        // Initialize the Google API Client.
        $client = new \Google\Client();
        $client->setAuthConfig($key_file_path);
        $client->addScope(\Google\Service\AnalyticsReporting::ANALYTICS_READONLY);

        $analytics = new \Google\Service\AnalyticsReporting($client);

        // Build the request.
        $request = new \Google\Service\AnalyticsReporting\ReportRequest([
            'viewId' => $view_id,
            'dateRanges' => [
                [
                    'startDate' => '7daysAgo',
                    'endDate'   => 'today',
                ],
            ],
            'metrics' => [
                ['expression' => 'ga:pageviews'],
            ],
            'dimensions' => [
                ['name' => 'ga:pagePath'],
            ],
            'orderBys' => [
                [
                    'fieldName' => 'ga:pageviews',
                    'sortOrder' => 'DESCENDING',
                ],
            ],
            'pageSize' => 5,
        ]);

        $body = new \Google\Service\AnalyticsReporting\GetReportsRequest([
            'reportRequests' => [$request],
        ]);

        try {
            $response = $analytics->reports->batchGet($body);

            $rows = $response->getReports()[0]->getData()->getRows();
            if (empty($rows)) {
                WP_CLI::success('No data found for the specified period.');
                return;
            }

            WP_CLI::log('Top 5 Posts (Last 7 Days):');
            foreach ($rows as $index => $row) {
                $path = $row->getDimensions()[0];
                $views = $row->getMetrics()[0]->getValues()[0];
                WP_CLI::log(sprintf("%d. %s - %s views", $index + 1, $path, $views));
            }

            WP_CLI::success('Data retrieved successfully!');
        } catch (\Exception $e) {
            WP_CLI::error('Error fetching data: ' . $e->getMessage());
        }
    }

    /**
     * Prompt the user for input in the CLI.
     *
     * @param string $message The prompt message.
     * @return string The user's input.
     */
    private function prompt_user($message) {
        fwrite(STDOUT, $message . ': ');
        $input = trim(fgets(STDIN));
        return $input;
    }
}

// Register the WP CLI command.
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('gatop', 'MWender\\GATopCommand\\GATopCommand', [
        'methods' => [
            'fetch',
            'configure',
        ],
    ]);
}

