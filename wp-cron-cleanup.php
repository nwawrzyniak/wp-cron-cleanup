<?php
/**
 * Plugin Name: WP Cron Cleanup
 * Description: A minimal plugin which adds an admin page in the tools section with a button to remove all upgrader_scheduled_cleanup cron events and reschedule only one.
 * Author: nwawrzyniak
 * Author URI:  https://nwawsoft.com/
 * Plugin URI:  https://github.com/nwawrzyniak/wp-cron-cleanup
 * License:     AGPL 3.0 or later
 * License URI: https://www.gnu.org/licenses/agpl-3.0.html
 * Version: 1.1.0
 */

if (!defined('ABSPATH')) {
  exit;
}

// Register the admin menu page
add_action('admin_menu', function () {
  add_management_page(
    'WP Cron Cleanup',
    'WP Cron Cleanup',
    'manage_options',
    'wp-cron-cleanup',
    'wp_cron_cleanup_page'
  );
});

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
  $settings_link = '<a href="tools.php?page=wp-cron-cleanup">Settings</a>';
  array_unshift($links, $settings_link);
  return $links;
});

// Helper to count events
function count_upgrader_scheduled_cleanup_events()
{
  $crons = _get_cron_array();
  $count = 0;

  if (is_array($crons)) {
    foreach ($crons as $timestamp => $cron) {
      if (isset($cron['upgrader_scheduled_cleanup'])) {
        $count += count($cron['upgrader_scheduled_cleanup']);
      }
    }
  }

  return $count;
}

// Unschedule all upgrader_scheduled_cleanup events
function remove_all_upgrader_cleanup_events()
{
  $crons = _get_cron_array();
  $removed = 0;

  if (is_array($crons)) {
    foreach ($crons as $timestamp => $cron) {
      if (isset($cron['upgrader_scheduled_cleanup'])) {
        foreach ($cron['upgrader_scheduled_cleanup'] as $args) {
          wp_unschedule_event($timestamp, 'upgrader_scheduled_cleanup', $args['args'] ?? []);
          $removed++;
        }
      }
    }
  }

  return $removed;
}

// Page rendering
function wp_cron_cleanup_page()
{
  $before = count_upgrader_scheduled_cleanup_events();
  $removed = 0;
  $scheduled_new = false;

  if (isset($_POST['cleanup_upgrader_cron']) && check_admin_referer('cleanup_upgrader_cron_action')) {
    $removed = remove_all_upgrader_cleanup_events();
    $after = count_upgrader_scheduled_cleanup_events();

    if ($after === 0) {
      wp_schedule_single_event(time() + 7200, 'upgrader_scheduled_cleanup');
      $after = 1;
      $scheduled_new = true;
    }

    echo '<div class="updated"><p><strong>Removed ' . esc_html($removed) . ' events.</strong><br>';
    echo 'Scheduled events before: <strong>' . esc_html($before) . '</strong><br>';
    echo 'Scheduled events after: <strong>' . esc_html($after) . '</strong>';
    if ($scheduled_new) {
      echo '<br><em>Scheduled one new <code>upgrader_scheduled_cleanup</code> event for 2 hours from now.</em>';
    }
    if (!wp_next_scheduled('upgrader_scheduled_cleanup') && !$scheduled_new) {
      echo '<br><strong style="color: red;">Warning:</strong> No event was scheduled and none could be found. Cron may be disabled.';
    }
    echo '</p></div>';
  } else {
    echo '<div class="notice notice-info"><p>';
    $next = wp_next_scheduled('upgrader_scheduled_cleanup');
    if ($next) {
      echo '<p>Next scheduled event: <strong>' . date('Y-m-d H:i:s', $next) . '</strong></p>';
    } else {
      echo '<p><em>No upcoming scheduled event found.</em></p>';
    }
    echo 'Scheduled <code>upgrader_scheduled_cleanup</code> events: <strong>' . esc_html($before) . '</strong>';
    echo '</p></div>';
  }
  ?>
  <div class="wrap">
    <h1>WP Cron Cleanup</h1>
    <form method="post">
      <?php wp_nonce_field('cleanup_upgrader_cron_action'); ?>
      <p>This will remove all scheduled <code>upgrader_scheduled_cleanup</code> cron events and ensure one fresh one is scheduled.</p>
      <p><input type="submit" name="cleanup_upgrader_cron" class="button button-primary" value="Remove Events and Schedule One"></p>
    </form>
  </div>
  <?php
}
