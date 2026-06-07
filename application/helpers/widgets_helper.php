<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin dashboard widgets
 * We are registering all widgets here
 * Also action hook is included to add new widgets if needed in my_functions_helper.php
 * @return array
 */
function get_dashboard_widgets()
{
    $widgets = [
        [
            'path'      => 'admin/dashboard/widgets/top_stats',
            'container' => 'top-12',
        ],
        [
            'path'      => 'admin/dashboard/widgets/finance_overview',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/user_data',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/upcoming_events',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/calendar',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/payments_chart',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/todos',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/leads_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/projects_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/tickets_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/projects_activity',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/contracts_expiring',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/tickets_report',
            'container' => 'left-8',
        ],
    ];

    return hooks()->apply_filters('get_dashboard_widgets', $widgets);
}

/**
 * Render widgets based on container
 * The function will check if staff have re-organized the dashboard and apply any order which is needed.
 * @param  string $container
 * @return mixed
 */
function render_dashboard_widgets($container)
{
    $widgetsHtml = [];

    static $widgets     = null;
    static $widgetsData = null;

    $CI = &get_instance();

    if (!$widgets) {
        $widgetsData       = [];
        $widgets           = get_dashboard_widgets();

        foreach ($widgets as $key => $widget) {
            try {
                $html = (string) $CI->load->view($widget['path'], [], true);
            } catch (Throwable $e) {
                log_message('error', 'Dashboard widget render failed for [' . ($widget['path'] ?? 'unknown') . ']: ' . $e->getMessage());
                unset($widgets[$key]);
                continue;
            }

            if ($html !== '') {
                $htmlID = dashboard_widget_extract_id($html);
                if ($htmlID !== '') {
                    $widgetsData[$htmlID] = [
                    'widgetIndex'     => $key,
                    'widgetPath'      => $widget['path'],
                    'widgetContainer' => $widget['container'],
                    'html'            => $html,
                    ];

                    $widgets[$key]['settingID'] = strafter($htmlID, 'widget-');
                    $widgets[$key]['html']      = $html;
                } else {
                    // Not compatible widget
                    unset($widgets[$key]);
                }
            } else {
                // Not compatible widget
                unset($widgets[$key]);
            }
        }
    }

    $staff_dashboard = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_order');
    $staff_dashboard = !$staff_dashboard ? [] : unserialize($staff_dashboard);

    if (count($staff_dashboard) == 0) {
        // Default widgets order and containers
        foreach ($widgets as $widget) {
            if ($widget['container'] == $container) {
                $widgetsHtml[$widget['settingID']] = $widget['html'];
            }
        }
    } else {
        $widgetsOutputted = [];
        if (isset($staff_dashboard[$container])) {
            foreach ($staff_dashboard[$container] as $widget) {
                if (isset($widgetsData[$widget])) {
                    array_push($widgetsOutputted, $widget);
                    $widgetsHtml[$widget] = $widgetsData[$widget]['html'];
                }
            }
        }

        foreach ($widgetsData as $wID => $widget) {
            // Widget exists but not applied in any staff container settings
            $applied = [];

            foreach ($staff_dashboard as $c => $w) {
                if (in_array($wID, $w)) {
                    array_push($applied, $wID);
                }
            }

            if ($widget['widgetContainer'] == $container && !in_array($wID, $applied)) {
                array_push($widgetsOutputted, $wID);
                $widgetsHtml[$wID] = $widget['html'];
            }
        }
    }

    $visibility = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility');
    $visibility = !$visibility ? [] : unserialize($visibility);
    foreach ($widgetsHtml as $widgetID => $widgetHTML) {
        foreach ($visibility as $option) {
            if ($option['id'] == strafter($widgetID, 'widget-') && $option['visible'] == 0) {
                if (dashboard_widget_has_html($widgetHTML) && !dashboard_widget_has_class($widgetHTML, 'hide')) {
                    $widgetHTML = dashboard_widget_add_class($widgetHTML, 'hide');
                }
            }
        }

        echo $widgetHTML;
    }
}

/**
 * Create widget ID from the given widget file
 *
 * @param  string|null $id
 *
 * @return string
 */
function create_widget_id($id = null)
{
    $id = basename($id ? $id : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0]['file'], '.php');

    if (startsWith($id, 'my_')) {
        $id = strafter($id, 'my_');
    }

    return $id;
}

function dashboard_widget_extract_id($html)
{
    if (!is_string($html) || trim($html) === '') {
        return '';
    }

    if (preg_match('/^\s*<([a-z0-9:_-]+)\b[^>]*\bid=(["\'])([^"\']+)\2/is', $html, $matches)) {
        return trim((string) ($matches[3] ?? ''));
    }

    return '';
}

function dashboard_widget_has_html($html)
{
    return is_string($html) && trim($html) !== '';
}

function dashboard_widget_has_class($html, $className)
{
    if (!dashboard_widget_has_html($html) || trim((string) $className) === '') {
        return false;
    }

    if (!preg_match('/^\s*<([a-z0-9:_-]+)\b([^>]*)>/is', $html, $matches)) {
        return false;
    }

    $attributes = (string) ($matches[2] ?? '');
    if (!preg_match('/\bclass=(["\'])([^"\']*)\1/is', $attributes, $classMatches)) {
        return false;
    }

    $classes = preg_split('/\s+/', trim((string) ($classMatches[2] ?? '')));
    return in_array($className, $classes, true);
}

function dashboard_widget_add_class($html, $className)
{
    if (!dashboard_widget_has_html($html) || trim((string) $className) === '') {
        return $html;
    }

    return preg_replace_callback('/^\s*<([a-z0-9:_-]+)\b([^>]*)>/is', function ($matches) use ($className) {
        $tagName = (string) ($matches[1] ?? 'div');
        $attributes = (string) ($matches[2] ?? '');

        if (preg_match('/\bclass=(["\'])([^"\']*)\1/is', $attributes, $classMatches)) {
            $existing = trim((string) ($classMatches[2] ?? ''));
            $classes = $existing === '' ? [] : preg_split('/\s+/', $existing);
            if (!in_array($className, $classes, true)) {
                $classes[] = $className;
            }

            $replacement = ' class="' . trim(implode(' ', array_filter($classes))) . '"';
            $attributes = preg_replace('/\bclass=(["\'])([^"\']*)\1/is', $replacement, $attributes, 1);
            return '<' . $tagName . $attributes . '>';
        }

        return '<' . $tagName . $attributes . ' class="' . $className . '">';
    }, $html, 1);
}
