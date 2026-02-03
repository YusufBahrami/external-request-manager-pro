<?php
/**
 * Deleted entries admin template
 */

if (!defined('ABSPATH')) exit;

?>
<div class="wrap">
    <h1><?php _e('Deleted Entries', 'erm-pro'); ?></h1>

    <table class="widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Host', 'erm-pro'); ?></th>
                <th><?php _e('Example URL', 'erm-pro'); ?></th>
                <th><?php _e('Was Blocked', 'erm-pro'); ?></th>
                <th><?php _e('Deleted At', 'erm-pro'); ?></th>
                <th><?php _e('Deleted By', 'erm-pro'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($results['data'])): ?>
                <?php foreach ($results['data'] as $row): ?>
                    <tr>
                        <td><?php echo esc_html($row->host); ?></td>
                        <td style="word-break:break-all;">
                            <?php
                            $full_url = isset($row->url_example) ? $row->url_example : '';
                            $max_chars = 80;
                            if (!empty($full_url)) {
                                $short = wp_html_excerpt($full_url, $max_chars);
                                if (mb_strlen($full_url) > $max_chars) {
                                    $short .= '...';
                                }
                            } else {
                                $short = '';
                            }
                            ?>
                            <span title="<?php echo esc_attr($full_url); ?>"><?php echo esc_html($short); ?></span>
                        </td>
                        <td><?php echo $row->was_blocked ? esc_html__('Yes', 'erm-pro') : esc_html__('No', 'erm-pro'); ?></td>
                        <td><?php echo esc_html($row->deleted_timestamp); ?></td>
                        <td>
                            <?php
                            $user = false;
                            if (!empty($row->deleted_by_user)) {
                                $user = get_userdata($row->deleted_by_user);
                            }
                            echo $user ? esc_html($user->display_name) : esc_html__('-', 'erm-pro');
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5"><?php _e('No deleted entries found.', 'erm-pro'); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <?php // Simple pagination
    $total_pages = max(1, ceil($results['total'] / $results['per_page']));
    if ($total_pages > 1): ?>
        <div class="tablenav">
            <div class="tablenav-pages">
                <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                    <?php if ($p == $results['paged']): ?>
                        <span class="page-numbers current"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a class="page-numbers" href="<?php echo esc_url(add_query_arg('paged', $p)); ?>"><?php echo $p; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
    <?php endif; ?>

</div>
