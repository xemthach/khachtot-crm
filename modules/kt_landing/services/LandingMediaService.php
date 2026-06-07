<?php

defined('BASEPATH') or exit('No direct script access allowed');

class LandingMediaService
{
    private $CI;
    private $model;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->model(KT_LANDING_MODULE . '/Kt_landing_model');
        $this->model = $this->CI->Kt_landing_model;
    }

    public function buildMediaDashboard()
    {
        $this->refreshUsageIndex();
        $media = $this->model->get_media();
        $rows = [];
        $summary = [
            'total' => count($media),
            'used' => 0,
            'unused' => 0,
            'large_files' => 0,
            'missing_alt' => 0,
        ];

        foreach ($media as $item) {
            $usage = $this->model->get_media_usage_graph((int) $item['id']);
            $item['usage_graph'] = $usage;
            $item['usage_count'] = (int) ($usage['total'] ?? 0);
            $item['usage_summary'] = $this->formatUsageSummary($usage);
            $item['is_large_file'] = ((int) ($item['file_size'] ?? 0)) >= 1048576 ? 1 : 0;
            $item['missing_alt'] = trim((string) ($item['alt_text'] ?? '')) === '' ? 1 : 0;
            $summary['used'] += $item['usage_count'] > 0 ? 1 : 0;
            $summary['unused'] += $item['usage_count'] > 0 ? 0 : 1;
            $summary['large_files'] += $item['is_large_file'];
            $summary['missing_alt'] += $item['missing_alt'];
            $rows[] = $item;
        }

        return [
            'summary' => $summary,
            'media' => $rows,
        ];
    }

    public function uploadMedia(array $input, array $files = [])
    {
        $fileField = $files['media_file'] ?? null;
        $result = null;
        $path = trim((string) ($input['file_path'] ?? ''));
        if (!empty($fileField) && !empty($fileField['name'])) {
            $result = $this->storeUploadedFile($fileField);
            if (empty($result['success'])) {
                return $result;
            }
            $path = (string) $result['path'];
        } elseif ($path === '') {
            return ['success' => false, 'message' => 'Please select a file or enter an existing file path'];
        }

        $meta = $this->extractMetadata($input);
        if (!empty($fileField) && !empty($fileField['name'])) {
            $meta['file_name'] = $result['file_name'];
            $meta['file_path'] = $path;
            $meta['file_type'] = $result['file_ext'];
            $meta['mime_type'] = $result['mime_type'];
            $meta['file_size'] = $result['file_size'];
            $meta['width'] = $result['width'];
            $meta['height'] = $result['height'];
        } else {
            $meta['file_name'] = basename($path);
            $meta['file_path'] = $path;
            $meta['file_type'] = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $meta['mime_type'] = '';
            $meta['file_size'] = !empty($input['file_size']) ? (int) $input['file_size'] : 0;
            $meta['width'] = !empty($input['width']) ? (int) $input['width'] : null;
            $meta['height'] = !empty($input['height']) ? (int) $input['height'] : null;
        }

        $saved = $this->model->save_media($meta);
        if (!$saved) {
            if (!empty($result['path'])) {
                $this->safeUnlink(FCPATH . $path);
            }
            return ['success' => false, 'message' => 'Unable to save media record'];
        }

        $createdId = is_numeric($saved) ? (int) $saved : 0;
        if ($createdId > 0) {
            $this->refreshUsageIndex($createdId);
            $this->logActivity('media.created', 'success', [
                'media_id' => $createdId,
                'file_path' => $path,
                'title' => (string) ($meta['title'] ?? ''),
            ]);
        }

        return ['success' => true, 'message' => 'Media uploaded'];
    }

    public function updateMedia($id, array $input, array $files = [])
    {
        $media = $this->model->get_media_by_id($id);
        if (!$media) {
            return ['success' => false, 'message' => 'Media not found'];
        }

        $meta = $this->extractMetadata($input);
        $newPath = null;
        $newUpload = $files['media_file'] ?? null;
        if (!empty($newUpload['name'])) {
            $result = $this->storeUploadedFile($newUpload);
            if (empty($result['success'])) {
                return $result;
            }
            $newPath = (string) $result['path'];
            $meta['file_name'] = $result['file_name'];
            $meta['file_path'] = $newPath;
            $meta['file_type'] = $result['file_ext'];
            $meta['mime_type'] = $result['mime_type'];
            $meta['file_size'] = $result['file_size'];
            $meta['width'] = $result['width'];
            $meta['height'] = $result['height'];
        } else {
            $manualPath = trim((string) ($input['file_path'] ?? ''));
            if ($manualPath !== '' && $manualPath !== (string) ($media['file_path'] ?? '')) {
                $newPath = $manualPath;
            }
            $meta['file_name'] = $newPath !== null ? basename($newPath) : (string) ($media['file_name'] ?? '');
            $meta['file_path'] = $newPath !== null ? $newPath : (string) ($media['file_path'] ?? '');
            $meta['file_type'] = $newPath !== null ? strtolower(pathinfo($newPath, PATHINFO_EXTENSION)) : (string) ($media['file_type'] ?? '');
            $meta['mime_type'] = (string) ($media['mime_type'] ?? '');
            $meta['file_size'] = (int) ($media['file_size'] ?? 0);
            $meta['width'] = isset($media['width']) ? (int) $media['width'] : null;
            $meta['height'] = isset($media['height']) ? (int) $media['height'] : null;
            if ($newPath === null) {
                $newPath = (string) ($media['file_path'] ?? '');
            }
        }

        $saved = $this->model->save_media($meta, $id);
        if (!$saved) {
            if (!empty($result['path'])) {
                $this->safeUnlink(FCPATH . $result['path']);
            }
            return ['success' => false, 'message' => 'Unable to save media record'];
        }

        if (!empty($newPath) && $newPath !== (string) ($media['file_path'] ?? '')) {
            $this->replaceMediaReferences((string) ($media['file_path'] ?? ''), $newPath);
            $oldPath = FCPATH . ltrim((string) ($media['file_path'] ?? ''), '/');
            if (strpos($oldPath, FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'kt_landing_media') === 0) {
                $this->safeUnlink($oldPath);
            }
        }

        $this->refreshUsageIndex((int) $id);
        $this->logActivity('media.updated', 'success', [
            'media_id' => (int) $id,
            'file_path' => (string) ($meta['file_path'] ?? ''),
            'title' => (string) ($meta['title'] ?? ''),
        ]);

        if (!empty($newUpload['name'])) {
            $this->logActivity('media.replaced', 'success', [
                'media_id' => (int) $id,
                'file_path' => (string) ($meta['file_path'] ?? ''),
            ]);
        }

        return ['success' => true, 'message' => !empty($newUpload['name']) ? 'Media replaced' : 'Media updated'];
    }

    public function canDeleteMedia($id)
    {
        return $this->model->can_delete_media($id);
    }

    public function deleteMedia($id)
    {
        $media = $this->model->get_media_by_id($id);
        if (!$media) {
            return ['success' => false, 'message' => 'Media not found'];
        }

        if (!$this->canDeleteMedia($id)) {
            $this->logActivity('media.delete_blocked', 'warning', [
                'media_id' => (int) $id,
                'file_path' => (string) ($media['file_path'] ?? ''),
            ]);
            return ['success' => false, 'message' => 'Media is in use and cannot be deleted'];
        }

        $filePath = FCPATH . ltrim((string) ($media['file_path'] ?? ''), '/');
        $deleted = $this->model->delete_media($id);
        if (!$deleted) {
            return ['success' => false, 'message' => 'Delete failed'];
        }

        if (is_file($filePath) && strpos($filePath, FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'kt_landing_media') === 0) {
            $this->safeUnlink($filePath);
        }

        $this->logActivity('media.deleted', 'warning', [
            'media_id' => (int) $id,
            'file_path' => (string) ($media['file_path'] ?? ''),
        ]);

        return ['success' => true, 'message' => 'Media deleted'];
    }

    public function refreshUsageIndex($mediaId = null)
    {
        if ($mediaId !== null) {
            $this->model->clear_media_usage((int) $mediaId);
            $this->reindexMedia((int) $mediaId);
            return true;
        }

        $media = $this->model->get_media();
        foreach ($media as $item) {
            $this->model->clear_media_usage((int) $item['id']);
            $this->reindexMedia((int) $item['id']);
        }

        return true;
    }

    private function reindexMedia($mediaId)
    {
        $media = $this->model->get_media_by_id($mediaId);
        if (!$media) {
            return;
        }

        $needles = $this->buildNeedles($media);
        if (empty($needles)) {
            $this->model->save_media([
                'folder' => $media['folder'],
                'file_name' => $media['file_name'],
                'file_path' => $media['file_path'],
                'file_type' => $media['file_type'],
                'mime_type' => $media['mime_type'] ?? '',
                'file_size' => $media['file_size'],
                'title' => $media['title'],
                'alt_text' => $media['alt_text'] ?? '',
                'caption' => $media['caption'] ?? '',
                'tags' => $media['tags'] ?? '',
                'category' => $media['category'] ?? '',
                'width' => $media['width'] ?? null,
                'height' => $media['height'] ?? null,
                'usage_count' => 0,
                'last_used_at' => null,
            ], $mediaId);
            return;
        }

        $usageRows = [];
        foreach ($this->collectSources() as $source) {
            $usageRows = array_merge($usageRows, $this->scanSourceForNeedles($source, $needles, $media));
        }

        $this->model->replace_media_usage($mediaId, $usageRows);

        $count = count($usageRows);
        $lastUsed = $count > 0 ? $this->now() : null;
        $this->model->save_media([
            'folder' => $media['folder'],
            'file_name' => $media['file_name'],
            'file_path' => $media['file_path'],
            'file_type' => $media['file_type'],
            'mime_type' => $media['mime_type'] ?? '',
            'file_size' => $media['file_size'],
            'title' => $media['title'],
            'alt_text' => $media['alt_text'] ?? '',
            'caption' => $media['caption'] ?? '',
            'tags' => $media['tags'] ?? '',
            'category' => $media['category'] ?? '',
            'width' => $media['width'] ?? null,
            'height' => $media['height'] ?? null,
            'usage_count' => $count,
            'last_used_at' => $lastUsed,
        ], $mediaId);
    }

    private function collectSources()
    {
        $sources = [];
        $sources[] = [
            'usage_type' => 'page',
            'usage_ref_type' => 'kt_landing_pages',
            'rows' => $this->model->get_pages(),
            'fields' => ['title', 'slug', 'seo_title', 'seo_description', 'template_code'],
        ];
        $sources[] = [
            'usage_type' => 'section',
            'usage_ref_type' => 'kt_landing_sections',
            'rows' => $this->model->get_sections(),
            'fields' => ['page_key', 'section_key', 'title', 'subtitle', 'content', 'image', 'icon', 'button_text', 'button_url', 'settings_json'],
        ];
        $sectionItems = $this->collectSectionItems();
        $sources[] = [
            'usage_type' => 'section',
            'usage_ref_type' => 'kt_landing_section_items',
            'rows' => $sectionItems,
            'fields' => ['item_key', 'title', 'subtitle', 'content', 'icon', 'image', 'badge', 'button_text', 'button_url', 'settings_json'],
        ];
        $sources[] = [
            'usage_type' => 'block',
            'usage_ref_type' => 'kt_landing_global_blocks',
            'rows' => $this->model->get_global_blocks(),
            'fields' => ['block_key', 'block_name', 'block_type', 'content_json'],
        ];
        $sources[] = [
            'usage_type' => 'blog',
            'usage_ref_type' => 'kt_landing_blog_posts',
            'rows' => $this->model->get_blog_posts(),
            'fields' => ['title', 'slug', 'excerpt', 'content', 'featured_image', 'category', 'tags', 'seo_title', 'seo_description'],
        ];
        $settings = $this->model->get_settings_map(['light_logo', 'dark_logo', 'favicon', 'og_image', 'primary_color', 'secondary_color', 'accent_color']);
        $sources[] = [
            'usage_type' => 'marketplace',
            'usage_ref_type' => 'kt_landing_settings',
            'rows' => [['id' => 0, 'setting_key' => 'media_settings', 'setting_value' => json_encode($settings)]],
            'fields' => ['setting_key', 'setting_value'],
        ];

        return $sources;
    }

    private function collectSectionItems()
    {
        $items = [];
        $sections = $this->model->get_sections();
        foreach ($sections as $section) {
            $sectionItems = $this->model->get_section_items((int) $section['id'], null, false);
            foreach ($sectionItems as $item) {
                $item['page_key'] = (string) ($section['page_key'] ?? 'home');
                $item['section_key'] = (string) ($section['section_key'] ?? '');
                $items[] = $item;
            }
        }
        return $items;
    }

    private function scanSourceForNeedles(array $source, array $needles, array $media)
    {
        $matches = [];
        foreach (($source['rows'] ?? []) as $row) {
            $usageType = (string) ($source['usage_type'] ?? 'page');
            if ($usageType === 'section') {
                $sectionKey = strtolower((string) ($row['section_key'] ?? $row['item_key'] ?? ''));
                $sectionTitle = strtolower((string) ($row['title'] ?? ''));
                if (strpos($sectionKey, 'faq') !== false || strpos($sectionTitle, 'faq') !== false) {
                    $usageType = 'faq';
                } elseif (strpos($sectionKey, 'addon') !== false || strpos($sectionKey, 'market') !== false) {
                    $usageType = 'marketplace';
                }
            }

            foreach (($source['fields'] ?? []) as $field) {
                $value = $row[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $valueString = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($this->containsAnyNeedle($valueString, $needles)) {
                    $matches[] = [
                        'usage_type' => $usageType,
                        'usage_ref_type' => (string) ($source['usage_ref_type'] ?? ''),
                        'usage_ref_id' => isset($row['id']) ? (int) $row['id'] : null,
                        'usage_ref_key' => (string) ($row['page_key'] ?? $row['section_key'] ?? $row['block_key'] ?? $row['slug'] ?? $row['setting_key'] ?? $row['item_key'] ?? ''),
                        'usage_label' => (string) ($row['title'] ?? $row['block_name'] ?? $row['setting_key'] ?? $row['slug'] ?? ''),
                        'source_field' => (string) $field,
                        'source_value' => $valueString,
                    ];
                }
            }
        }

        return $matches;
    }

    private function buildNeedles(array $media)
    {
        $needles = [];
        foreach ([
            (string) ($media['file_path'] ?? ''),
            (string) ($media['file_name'] ?? ''),
            basename((string) ($media['file_path'] ?? '')),
        ] as $needle) {
            $needle = trim($needle);
            if ($needle !== '' && !in_array($needle, $needles, true)) {
                $needles[] = $needle;
            }
        }
        return $needles;
    }

    private function containsAnyNeedle($haystack, array $needles)
    {
        $haystack = (string) $haystack;
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($haystack, $needle) !== false) {
                return true;
            }
            $base = basename($needle);
            if ($base !== '' && stripos($haystack, $base) !== false) {
                return true;
            }
        }
        return false;
    }

    private function extractMetadata(array $input)
    {
        return [
            'folder' => trim((string) ($input['folder'] ?? 'landing')) ?: 'landing',
            'title' => trim((string) ($input['title'] ?? '')),
            'alt_text' => trim((string) ($input['alt_text'] ?? '')),
            'caption' => trim((string) ($input['caption'] ?? '')),
            'tags' => trim((string) ($input['tags'] ?? '')),
            'category' => trim((string) ($input['category'] ?? '')),
        ];
    }

    private function storeUploadedFile($file)
    {
        if (empty($file) || empty($file['name'])) {
            return ['success' => false, 'message' => 'Please select a file'];
        }

        if (!empty($file['error'])) {
            return ['success' => false, 'message' => 'Upload failed'];
        }

        $dir = FCPATH . 'uploads/kt_landing_media/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $originalName = (string) $file['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        $security = $this->validateUploadedFile($originalName, (string) ($file['tmp_name'] ?? ''), (int) ($file['size'] ?? 0));
        if (!$security['success']) {
            return $security;
        }

        $storedName = $this->randomFileName($ext);
        $destination = $dir . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            return ['success' => false, 'message' => 'Upload failed'];
        }

        $relative = 'uploads/kt_landing_media/' . $storedName;
        $imageInfo = @getimagesize($destination);
        return [
            'success' => true,
            'path' => $relative,
            'file_name' => $storedName,
            'file_ext' => $ext,
            'mime_type' => $security['mime_type'],
            'file_size' => (int) ($file['size'] ?? filesize($destination)),
            'width' => is_array($imageInfo) ? (int) ($imageInfo[0] ?? 0) : null,
            'height' => is_array($imageInfo) ? (int) ($imageInfo[1] ?? 0) : null,
        ];
    }

    private function validateUploadedFile($filename, $tmpPath, $size)
    {
        $allowedExtensions = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'avif' => ['image/avif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'txt' => ['text/plain'],
        ];
        $blockedExtensions = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phar', 'cgi', 'pl', 'py', 'sh', 'svg'];

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($extension === '' || in_array($extension, $blockedExtensions, true) || !isset($allowedExtensions[$extension])) {
            return ['success' => false, 'message' => 'File type is not allowed'];
        }

        $parts = array_map('strtolower', explode('.', $filename));
        array_pop($parts);
        if (array_intersect($parts, $blockedExtensions)) {
            return ['success' => false, 'message' => 'File name is not allowed'];
        }

        if ($size <= 0 || $size > 20 * 1024 * 1024) {
            return ['success' => false, 'message' => 'File size is not allowed'];
        }

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            return ['success' => false, 'message' => 'Upload failed'];
        }

        $mimeType = $this->detectMimeType($tmpPath);
        if ($mimeType === '' || !in_array($mimeType, $allowedExtensions[$extension], true)) {
            return ['success' => false, 'message' => 'File content type is not allowed'];
        }

        return ['success' => true, 'mime_type' => $mimeType];
    }

    private function detectMimeType($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                return is_string($mime) ? $mime : '';
            }
        }

        return '';
    }

    private function randomFileName($extension)
    {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $token = md5(uniqid('', true));
        }

        return $token . '.' . $extension;
    }

    private function replaceMediaReferences($oldPath, $newPath)
    {
        $oldPath = trim((string) $oldPath);
        $newPath = trim((string) $newPath);
        if ($oldPath === '' || $newPath === '' || $oldPath === $newPath) {
            return;
        }

        $tableConfigs = [
            db_prefix() . 'kt_landing_sections' => ['title', 'subtitle', 'content', 'image', 'icon', 'button_text', 'button_url', 'settings_json'],
            db_prefix() . 'kt_landing_section_items' => ['title', 'subtitle', 'content', 'icon', 'image', 'badge', 'button_text', 'button_url', 'settings_json'],
            db_prefix() . 'kt_landing_global_blocks' => ['block_name', 'content_json'],
            db_prefix() . 'kt_landing_blog_posts' => ['title', 'excerpt', 'content', 'featured_image', 'seo_title', 'seo_description'],
            db_prefix() . 'kt_landing_pages' => ['title', 'seo_title', 'seo_description'],
            db_prefix() . 'kt_landing_settings' => ['setting_value'],
        ];

        $replacements = [
            $oldPath => $newPath,
            base_url($oldPath) => base_url($newPath),
            basename($oldPath) => basename($newPath),
        ];

        foreach ($tableConfigs as $table => $fields) {
            if (!$this->CI->db->table_exists($table)) {
                continue;
            }

            $rows = $this->CI->db->get($table)->result_array();
            foreach ($rows as $row) {
                $payload = [];
                $changed = false;
                foreach ($fields as $field) {
                    if (!array_key_exists($field, $row)) {
                        continue;
                    }
                    $value = (string) ($row[$field] ?? '');
                    $updated = str_replace(array_keys($replacements), array_values($replacements), $value, $count);
                    if ($count > 0) {
                        $payload[$field] = $updated;
                        $changed = true;
                    }
                }
                if ($changed && !empty($row['id'])) {
                    $this->CI->db->where('id', (int) $row['id'])->update($table, $payload);
                }
            }
        }
    }

    private function formatUsageSummary(array $graph)
    {
        $parts = [];
        foreach ((array) ($graph['by_type'] ?? []) as $type => $count) {
            $parts[] = ucfirst((string) $type) . ': ' . (int) $count;
        }
        return empty($parts) ? 'Unused' : implode(' · ', $parts);
    }

    private function safeUnlink($path)
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function logActivity($action, $severity, array $context = [])
    {
        $this->CI->load->model('kt_saas/Kt_saas_model');
        if (isset($this->CI->Kt_saas_model) && method_exists($this->CI->Kt_saas_model, 'log_activity')) {
            $this->CI->Kt_saas_model->log_activity($action, $severity, $context);
        }
    }

    private function now()
    {
        return date('Y-m-d H:i:s');
    }
}
