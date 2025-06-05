<?php
/**
 * Event Logging API (Continued)
 * Handles impression, click, and conversion tracking
 */

            $db->query(
                "UPDATE users SET balance = balance - ? WHERE id = ?",
                [$requestData['cost'], $campaign['user_id']]
            );
            
            // Update campaign spent amount
            $db->query(
                "UPDATE campaigns SET spent_amount = spent_amount + ? WHERE id = ?",
                [$requestData['cost'], $campaignId]
            );
            
            // Log transaction
            $advertiserBalance = $db->fetch(
                "SELECT balance FROM users WHERE id = ?",
                [$campaign['user_id']]
            );
            
            $db->insert('transactions', [
                'user_id' => $campaign['user_id'],
                'transaction_type' => TRANSACTION_TYPE_SPENDING,
                'amount' => $requestData['cost'],
                'balance_before' => $advertiserBalance['balance'] + $requestData['cost'],
                'balance_after' => $advertiserBalance['balance'],
                'description' => ucfirst($event) . ' cost',
                'reference_type' => 'campaign',
                'reference_id' => $campaignId
            ]);
        }
    }
    
    // Return 1x1 transparent GIF for tracking pixels
    header('Content-Type: image/gif');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 1x1 transparent GIF
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    
} catch (Exception $e) {
    logMessage("Event logging error: " . $e->getMessage(), 'ERROR');
    
    // Still return tracking pixel to avoid breaking ads
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}
?>
