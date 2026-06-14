<?php
/** Renders queued flash messages and validation errors, then clears them. */

use App\Core\Flash;
use App\Core\Session;

$messages = Flash::pull();
$validationErrors = (array) Session::get('errors', []);
Session::remove('errors');
Session::remove('old');
?>
<?php foreach ($messages as $type => $message): ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
        <?= e($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>

<?php if ($validationErrors !== []): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-1">
            <?php foreach ($validationErrors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
