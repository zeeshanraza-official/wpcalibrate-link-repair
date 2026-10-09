/**
 * WPCalibrate Link Repair - Admin Application Script
 */
(function ($) {
    'use strict';

    const config = window.WPCalibrateLR || {};
    let pollTimer = null;
    let isProcessingStep = false;
    let currentPage = 1;

    // Helper for REST requests
    function apiRequest(endpoint, method = 'GET', data = null) {
        return $.ajax({
            url: config.restUrl + endpoint,
            method: method,
            data: data ? JSON.stringify(data) : null,
            contentType: 'application/json',
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            }
        });
    }

    // -------------------------------------------------------------
    // SCAN LIFECYCLE & POLLING
    // -------------------------------------------------------------

    function updateMonitorUI(scan, counts) {
        const isRunning = scan && scan.status === 'running';
        const isPaused = scan && scan.status === 'paused';
        const status = scan ? scan.status : 'idle';

        $('#monitor-indicator').attr('class', 'status-indicator status-' + status);
        $('#monitor-status-label').text(status.charAt(0).toUpperCase() + status.slice(1));
        
        if (scan && scan.current_step) {
            $('#monitor-step-text').text(scan.current_step);
        }

        if (isRunning || isPaused) {
            $('#btn-start-scan').hide();
            $('#btn-cancel-scan').show();
            $('#monitor-progress-wrap').show();

            if (isRunning) {
                $('#btn-pause-scan').show();
                $('#btn-resume-scan').hide();
            } else {
                $('#btn-pause-scan').hide();
                $('#btn-resume-scan').show();
            }

            // Estimate progress
            let percent = 10;
            if (scan.stage === 'validation' && scan.total_discovered > 0) {
                percent = Math.min(98, Math.max(10, Math.round((scan.total_checked / scan.total_discovered) * 100)));
            } else if (scan.stage === 'discovery') {
                percent = 25;
            }
            $('#monitor-progress-fill').css('width', percent + '%');
        } else {
            $('#btn-start-scan').show();
            $('#btn-pause-scan').hide();
            $('#btn-resume-scan').hide();
            $('#btn-cancel-scan').hide();
            $('#monitor-progress-wrap').hide();
            $('#monitor-progress-fill').css('width', '0%');
        }

        if (scan && scan.started_at) {
            $('#monitor-last-started').text(scan.started_at);
        }
        if (scan && scan.completed_at) {
            $('#monitor-last-completed').text(scan.completed_at);
        }

        // Update counts
        if (counts) {
            $('#count-broken').text(counts.broken || 0);
            $('#count-redirect').text(counts.redirect || 0);
            $('#count-warning').text(counts.warning || 0);
            $('#count-working').text(counts.working || 0);
            $('#count-total').text(counts.total || 0);
        }
    }

    function stepOrPoll() {
        if (isProcessingStep) return;
        isProcessingStep = true;

        apiRequest('scans/step', 'POST')
            .done(function (res) {
                if (res.success) {
                    updateMonitorUI(res.scan, res.counts);
                    if (res.scan && res.scan.status === 'running') {
                        pollTimer = setTimeout(stepOrPoll, 1200);
                    } else {
                        // Scan finished, update UI and reload table
                        clearTimeout(pollTimer);
                        updateMonitorUI(res.scan, res.counts);
                        loadUrlsTable(currentPage);
                    }
                }
            })
            .always(function () {
                isProcessingStep = false;
            });
    }

    function checkInitialScanState() {
        apiRequest('scans/status', 'GET')
            .done(function (res) {
                if (res.success) {
                    updateMonitorUI(res.active_scan || res.latest_scan, res.counts);
                    if (res.active_scan && res.active_scan.status === 'running') {
                        stepOrPoll();
                    }
                }
            });
    }

    // -------------------------------------------------------------
    // BUTTON HANDLERS
    // -------------------------------------------------------------

    $('#wpcalibrate-lr-scan-now-btn, #btn-start-scan').on('click', function () {
        const $btn = $(this);
        $btn.prop('disabled', true);

        apiRequest('scans/start', 'POST')
            .done(function (res) {
                if (res.success) {
                    checkInitialScanState();
                }
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || config.strings.errorOccurred);
            })
            .always(function () {
                $btn.prop('disabled', false);
            });
    });

    $('#btn-pause-scan').on('click', function () {
        clearTimeout(pollTimer);
        apiRequest('scans/pause', 'POST')
            .done(function () {
                checkInitialScanState();
            });
    });

    $('#btn-resume-scan').on('click', function () {
        apiRequest('scans/resume', 'POST')
            .done(function () {
                checkInitialScanState();
            });
    });

    $('#btn-cancel-scan').on('click', function () {
        if (!confirm(config.strings.confirmCancel)) return;
        clearTimeout(pollTimer);
        apiRequest('scans/cancel', 'POST')
            .done(function () {
                checkInitialScanState();
            });
    });

    // -------------------------------------------------------------
    // URLS DATA TABLE (SERVER-SIDE PAGINATION)
    // -------------------------------------------------------------

    function loadUrlsTable(page = 1) {
        const $tableBody = $('#urls-table-body');
        if (!$tableBody.length) return;

        currentPage = page;
        const status = $('#url-status-filter').val() || 'broken';
        const search = $('#url-search-input').val() || '';

        $tableBody.html('<tr><td colspan="6" class="loading-cell"><span class="spinner is-active" style="float:none;"></span> Loading...</td></tr>');

        apiRequest('urls?page=' + page + '&status=' + encodeURIComponent(status) + '&search=' + encodeURIComponent(search))
            .done(function (res) {
                if (!res.success || !res.items.length) {
                    $tableBody.html('<tr><td colspan="6" style="text-align:center; padding:20px; color:#646970;">No links matching this criteria.</td></tr>');
                    $('#pagination-info').text('0 items');
                    $('#pagination-buttons').empty();
                    return;
                }

                renderTableRows(res.items);
                renderPagination(res.page, res.total_pages, res.total);
            })
            .fail(function () {
                $tableBody.html('<tr><td colspan="6" style="color:#d63638; text-align:center;">Failed to load links.</td></tr>');
            });
    }

    function renderTableRows(items) {
        const $tableBody = $('#urls-table-body');
        $tableBody.empty();

        items.forEach(function (item) {
            const statusClass = 'status-' + (item.status || 'pending');
            const row = $(`
                <tr data-url-id="${item.id}" data-url="${escapeHtml(item.url)}">
                    <td>
                        <span class="link-type-badge">${escapeHtml(item.link_type)}</span>
                        <a href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer" class="link-target">
                            ${escapeHtml(item.url)}
                        </a>
                    </td>
                    <td><span class="status-badge ${statusClass}">${escapeHtml(item.status)}</span></td>
                    <td><code>${item.http_code ? item.http_code : '—'}</code></td>
                    <td>
                        <button type="button" class="button button-small btn-toggle-occurrences" data-url-id="${item.id}">
                            ${item.occurrence_count || 1} &darr;
                        </button>
                    </td>
                    <td><small>${escapeHtml(item.last_checked_at || 'Never')}</small></td>
                    <td>
                        <div class="row-actions-wrap" style="display:flex; gap:4px; flex-wrap:wrap;">
                            <button type="button" class="button button-small btn-open-replace" data-url-id="${item.id}" data-url="${escapeHtml(item.url)}" title="Replace URL">Replace</button>
                            <button type="button" class="button button-small btn-recheck-url" data-url-id="${item.id}" title="Recheck Health">Recheck</button>
                            <button type="button" class="button button-small btn-ignore-url" data-url-id="${item.id}" data-ignored="${item.is_ignored}">${item.is_ignored == 1 ? 'Unignore' : 'Ignore'}</button>
                            <button type="button" class="button button-small btn-whitelist-url" data-url-id="${item.id}" title="Whitelist this URL">Whitelist</button>
                        </div>
                    </td>
                </tr>
                <tr class="drawer-row" id="drawer-${item.id}" style="display:none;">
                    <td colspan="6">
                        <div class="occurrences-drawer" id="drawer-content-${item.id}">
                            <span class="spinner is-active" style="float:none;"></span> Loading occurrences...
                        </div>
                    </td>
                </tr>
            `);
            $tableBody.append(row);
        });
    }

    function renderPagination(page, totalPages, totalItems) {
        $('#pagination-info').text(totalItems + ' items');
        const $btns = $('#pagination-buttons').empty();

        if (totalPages <= 1) return;

        if (page > 1) {
            $btns.append(`<button type="button" class="button button-small btn-page" data-page="${page - 1}">&laquo; Prev</button> `);
        }
        $btns.append(`<span> Page ${page} of ${totalPages} </span> `);
        if (page < totalPages) {
            $btns.append(`<button type="button" class="button button-small btn-page" data-page="${page + 1}">Next &raquo;</button>`);
        }
    }

    $(document).on('click', '.btn-page', function () {
        loadUrlsTable($(this).data('page'));
    });

    $('#btn-search-urls, #btn-refresh-table').on('click', function () {
        loadUrlsTable(1);
    });

    $('#url-status-filter').on('change', function () {
        loadUrlsTable(1);
    });

    // -------------------------------------------------------------
    // OCCURRENCES DRAWER
    // -------------------------------------------------------------

    $(document).on('click', '.btn-toggle-occurrences', function () {
        const urlId = $(this).data('url-id');
        const $drawer = $('#drawer-' + urlId);
        const $content = $('#drawer-content-' + urlId);

        if ($drawer.is(':visible')) {
            $drawer.hide();
            return;
        }

        $drawer.show();
        apiRequest('urls/' + urlId + '/occurrences')
            .done(function (res) {
                if (!res.success || !res.occurrences.length) {
                    $content.html('<p style="margin:0; color:#646970;">No specific source occurrences found.</p>');
                    return;
                }

                let html = '<div class="occurrences-list" style="display:flex; flex-direction:column; gap:8px;">';
                res.occurrences.forEach(function (occ) {
                    const editLink = occ.edit_url ? `<a href="${escapeHtml(occ.edit_url)}" target="_blank" class="button button-small">Edit in WP</a>` : '';
                    const unlinkBtn = `<button type="button" class="button button-small btn-unlink-occurrence" data-occ-id="${occ.id}">Unlink</button>`;
                    html += `
                        <div class="occurrence-item">
                            <div>
                                <strong>${escapeHtml(occ.title)}</strong> 
                                <span style="color:#646970;">(${escapeHtml(occ.source_type)} &bull; ${escapeHtml(occ.field_key)})</span>
                                ${occ.anchor_text ? `<div style="font-size:12px; color:#50575e; margin-top:2px;">Anchor: "<em>${escapeHtml(occ.anchor_text)}</em>"</div>` : ''}
                            </div>
                            <div style="display:flex; gap:6px;">
                                ${unlinkBtn}
                                ${editLink}
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                $content.html(html);
            });
    });

    // -------------------------------------------------------------
    // ACTIONS: RECHECK, IGNORE, WHITELIST, UNLINK
    // -------------------------------------------------------------

    $(document).on('click', '.btn-recheck-url', function () {
        const $btn = $(this);
        const urlId = $btn.data('url-id');
        $btn.prop('disabled', true).text('Checking...');

        apiRequest('urls/' + urlId + '/recheck', 'POST')
            .done(function (res) {
                loadUrlsTable(currentPage);
            })
            .always(function () {
                $btn.prop('disabled', false).text('Recheck');
            });
    });

    $(document).on('click', '.btn-ignore-url', function () {
        const urlId = $(this).data('url-id');
        const isIgnored = $(this).data('ignored') == 1;

        apiRequest('urls/' + urlId + '/ignore', 'POST', { ignored: !isIgnored })
            .done(function () {
                loadUrlsTable(currentPage);
            });
    });

    $(document).on('click', '.btn-whitelist-url', function () {
        const urlId = $(this).data('url-id');
        if (!confirm('Exclude this URL from all future scans?')) return;

        apiRequest('urls/' + urlId + '/whitelist', 'POST')
            .done(function () {
                alert('URL added to whitelist.');
                loadUrlsTable(currentPage);
            });
    });

    $(document).on('click', '.btn-unlink-occurrence', function () {
        const occId = $(this).data('occ-id');
        if (!confirm(config.strings.confirmUnlink)) return;

        apiRequest('repair/unlink', 'POST', { occurrence_id: occId })
            .done(function (res) {
                alert(res.message);
                loadUrlsTable(currentPage);
            });
    });

    // -------------------------------------------------------------
    // BULK REPLACE MODAL
    // -------------------------------------------------------------

    let activeModalUrlId = null;

    $(document).on('click', '.btn-open-replace', function () {
        activeModalUrlId = $(this).data('url-id');
        const oldUrl = $(this).data('url');

        $('#modal-old-url').text(oldUrl);
        $('#modal-new-url-input').val('');
        $('#modal-preview-results').empty();
        $('#modal-bulk-replace').fadeIn(150);
    });

    $('.modal-close-btn, #modal-cancel-btn').on('click', function () {
        $('#modal-bulk-replace').fadeOut(150);
    });

    $('#modal-new-url-input').on('blur change', function () {
        const newUrl = $(this).val().trim();
        if (!newUrl || !activeModalUrlId) return;

        $('#modal-preview-loading').show();
        apiRequest('repair/preview', 'POST', { url_id: activeModalUrlId, new_url: newUrl })
            .done(function (res) {
                if (res.success && res.preview) {
                    $('#modal-preview-results').html(`
                        <div class="notice notice-warning inline" style="margin:0;">
                            <p><strong>Preview:</strong> This replacement will update <strong>${res.preview.total_count}</strong> occurrence(s) across your site.</p>
                        </div>
                    `);
                }
            })
            .always(function () {
                $('#modal-preview-loading').hide();
            });
    });

    $('#modal-confirm-replace-btn').on('click', function () {
        const newUrl = $('#modal-new-url-input').val().trim();
        if (!newUrl) {
            alert('Please specify a valid replacement URL.');
            return;
        }

        if (!confirm(config.strings.confirmBulk)) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('Replacing...');

        apiRequest('repair/replace', 'POST', {
            scope: 'bulk',
            url_id: activeModalUrlId,
            new_url: newUrl
        })
            .done(function (res) {
                alert(res.message);
                $('#modal-bulk-replace').fadeOut(150);
                loadUrlsTable(currentPage);
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || config.strings.errorOccurred);
            })
            .always(function () {
                $btn.prop('disabled', false).text('Confirm & Replace');
            });
    });

    // -------------------------------------------------------------
    // REDIRECTS VIEW
    // -------------------------------------------------------------

    function loadRedirectsTable(page = 1) {
        const $tableBody = $('#redirects-table-body');
        if (!$tableBody.length) return;

        const search = $('#redirect-search-input').val() || '';
        $tableBody.html('<tr><td colspan="5" class="loading-cell"><span class="spinner is-active" style="float:none;"></span> Loading redirects...</td></tr>');

        apiRequest('urls?page=' + page + '&status=redirect&search=' + encodeURIComponent(search))
            .done(function (res) {
                if (!res.success || !res.items.length) {
                    $tableBody.html('<tr><td colspan="5" style="text-align:center; padding:20px; color:#646970;">No redirects detected.</td></tr>');
                    return;
                }

                $tableBody.empty();
                res.items.forEach(function (item) {
                    const dest = item.redirect_url ? `<a href="${escapeHtml(item.redirect_url)}" target="_blank">${escapeHtml(item.redirect_url)}</a>` : '<em style="color:#646970;">Destination not verified</em>';
                    const applyBtn = item.redirect_url ? `<button type="button" class="button button-primary button-small btn-apply-redirect" data-url-id="${item.id}">Update to Destination</button>` : '';

                    $tableBody.append(`
                        <tr>
                            <td><a href="${escapeHtml(item.url)}" target="_blank">${escapeHtml(item.url)}</a></td>
                            <td><code>${item.http_code || 301}</code></td>
                            <td>${dest}</td>
                            <td>${item.occurrence_count || 1}</td>
                            <td>${applyBtn}</td>
                        </tr>
                    `);
                });
            });
    }

    $(document).on('click', '.btn-apply-redirect', function () {
        const urlId = $(this).data('url-id');
        if (!confirm('Update all occurrences of this link to the verified final destination?')) return;

        const $btn = $(this);
        $btn.prop('disabled', true).text('Updating...');

        apiRequest('repair/redirect', 'POST', { url_id: urlId })
            .done(function (res) {
                alert(res.message);
                loadRedirectsTable(1);
            })
            .always(function () {
                $btn.prop('disabled', false).text('Update to Destination');
            });
    });

    $('#btn-search-redirects, #btn-refresh-redirects').on('click', function () {
        loadRedirectsTable(1);
    });

    // Escape helper
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize
    $(document).ready(function () {
        checkInitialScanState();
        loadUrlsTable(1);
        loadRedirectsTable(1);
    });

})(jQuery);
