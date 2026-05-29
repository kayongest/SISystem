<?php
/**
 * Redirect proxy for legacy item view URLs.
 * Some QR codes and hardcoded links may point to items/view.php?id=X
 * This safely redirects them to the new items.php controller.
 */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
header("Location: ../items.php?action=view&id=" . $id);
exit();
