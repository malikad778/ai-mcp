/* AI MCP Admin JS v2 */
jQuery(function($) {

    // --- Regenerate all JSON ---
    $('#ai-mcp-regen-btn').on('click', function() {
        var $btn = $(this);
        var $status = $('#ai-mcp-regen-status');
        $btn.prop('disabled', true).text('⏳ Regenerating...');
        $status.text('');

        $.post(aiMCP.ajaxUrl, {
            action: 'AI_MCP_regenerate',
            nonce:  aiMCP.nonce
        }, function(res) {
            $btn.prop('disabled', false).text('🔄 Regenerate All JSON Now');
            if (res.success) {
                $status.css('color', '#155724').text('✅ Done! Generated at: ' + res.data.timestamp);
                $('#ai-mcp-last-gen').text(res.data.timestamp);
            } else {
                $status.css('color', '#721c24').text('❌ Error: ' + (res.data || 'Unknown error'));
            }
        }).fail(function() {
            $btn.prop('disabled', false).text('🔄 Regenerate All JSON Now');
            $status.css('color', '#721c24').text('❌ Request failed. Check server logs.');
        });
    });

    // --- Clear error log ---
    $('#ai-mcp-clear-errors').on('click', function() {
        if (!confirm('Clear all error log entries?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(aiMCP.ajaxUrl, {
            action: 'AI_MCP_clear_errors',
            nonce:  aiMCP.nonce
        }, function(res) {
            if (res.success) {
                $('#ai-mcp-error-status').css('color','#155724').text('✅ Cleared.');
                $btn.closest('table').remove();
            } else {
                $btn.prop('disabled', false);
                $('#ai-mcp-error-status').css('color','#721c24').text('❌ Failed.');
            }
        });
    });

    // --- Clear analytics ---
    $('#ai-mcp-clear-analytics').on('click', function() {
        if (!confirm('Clear all analytics data? This cannot be undone.')) return;
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(aiMCP.ajaxUrl, {
            action: 'AI_MCP_clear_analytics',
            nonce:  aiMCP.nonce
        }, function(res) {
            if (res.success) {
                $('#ai-mcp-analytics-status').css('color','#155724').text('✅ Analytics cleared. Refresh to see updated data.');
            } else {
                $btn.prop('disabled', false);
                $('#ai-mcp-analytics-status').css('color','#721c24').text('❌ Failed.');
            }
        });
    });
});
