<?php
/**
 * Renders (and clears) any pending flash message.
 * Safe to include on any page; outputs nothing when the session has none.
 */
$__f = flash_pop();
if ($__f):
?>
  <div class="flash flash-<?= e($__f['type']) ?>"><?= e($__f['message']) ?></div>
<?php endif;
