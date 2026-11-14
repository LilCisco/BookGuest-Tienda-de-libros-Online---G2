<?php
// Comparte el registro y el perfil; el modo de edición habilita los campos que se pueden guardar.
if ($page === 'registro'): ?>
<section class="register-card">
    <div class="register-brand"><i class="fa-solid fa-book-bookmark" aria-hidden="true"></i></div>
    <h1 class="register-title"><?= e(tr('Crea una nueva cuenta', 'Create a new account')) ?></h1>
    <p class="register-subtitle"><?= e(tr('Bienvenido(a)! Por favor ingresa tus datos.', 'Welcome! Please enter your details.')) ?></p>
    <?php renderNotice($notice); ?>
    <form action="api/cuenta.php" method="post" class="bookguest-register-form" data-password-match="<?= e(tr('Las contraseñas deben coincidir', 'Passwords must match')) ?>">
        <?= actionFields('registro') ?>
        <?php foreach ([
            ['nombre', 'text', 50, 'given-name', tr('Nombre', 'First name'), tr('Ingresa tu nombre *', 'Enter your first name *'), true],
            ['apellidos', 'text', 75, 'family-name', tr('Apellidos', 'Last name'), tr('Ingresa tus apellidos *', 'Enter your last name *'), true],
            ['email', 'email', 75, 'email', 'Email', tr('Ingresa tu correo electrónico *', 'Enter your email address *'), true],
            ['telefono', 'tel', 25, 'tel', tr('Teléfono', 'Phone'), tr('Ingresa tu número teléfono (opcional)', 'Enter your phone number (optional)'), false],
            ['direccion', 'text', 255, 'street-address', tr('Dirección domiciliaria', 'Home address'), tr('Ingresa tu dirección (opcional)', 'Enter your address (optional)'), false],
            ['password', 'password', 72, 'new-password', tr('Contraseña', 'Password'), tr('Crea una contraseña segura *', 'Create a secure password *'), true],
            ['confirmacion', 'password', 72, 'new-password', tr('Confirmación de contraseña', 'Confirm password'), tr('Confirma tu contraseña *', 'Confirm your password *'), true],
        ] as [$name, $type, $max, $autocomplete, $label, $placeholder, $required]): ?>
        <div class="auth-field">
            <label for="register-<?= e($name) ?>" class="register-label"><?= e($label) ?></label>
            <input id="register-<?= e($name) ?>" class="text-control register-control" name="<?= e($name) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" autocomplete="<?= e($autocomplete) ?>" placeholder="<?= e($placeholder) ?>"<?= $type === 'password' ? ' minlength="8"' : '' ?><?= $required ? ' required' : '' ?>>
            <?php if ($name === 'password'): ?><p class="register-help"><?= e(tr('Debe contener 8 caracteres como mínimo', 'Must contain at least 8 characters')) ?></p><?php endif ?>
            <?php if ($name === 'confirmacion'): ?><p class="register-help" data-confirmation-error hidden><?= e(tr('Las contraseñas deben coincidir', 'Passwords must match')) ?></p><?php endif ?>
        </div>
        <?php endforeach ?>
        <button type="submit" class="register-button"><?= e(tr('Registrarse', 'Sign up')) ?></button>
    </form>
    <div class="register-link-text"><?= e(tr('Ya tienes una cuenta?', 'Already have an account?')) ?> <?= linkTo('login', tr('Inicia sesión', 'Sign in'), [], 'register-link') ?></div>
</section>
<?php elseif ($page === 'registro-confirmado'): ?>
<section class="confirmation-card">
    <div class="form-icon"><i class="fa-solid fa-book-bookmark" aria-hidden="true"></i></div>
    <h1 class="confirmation-title"><?= e(tr('Tu cuenta ha sido creada con éxito', 'Your account has been created successfully')) ?></h1>
    <p class="confirmation-text"><?= e(tr('Ahora puedes iniciar sesión con el correo electrónico y la contraseña registrados.', 'You can now sign in with your registered email address and password.')) ?></p>
    <?= linkTo('login', tr('Iniciar sesión', 'Sign in'), [], 'btn-auth') ?>
</section>
<?php else:
    $editing = textInput($_GET, 'editar') === '1';
    $confirmed = textInput($_GET, 'confirmado') === '1';
    $avatar = imageUrl($account['ruta_imagen']);
    $prefix = $account['rol'] === ADMIN ? 'admin-profile' : 'profile';
?>
<section class="<?= $prefix ?>-card">
    <div class="<?= $prefix ?>-card-header">
        <h1 class="<?= $prefix ?>-card-title"><?= e(tr('Cuenta de BookGuest', 'BookGuest account')) ?></h1>
        <?php if ($editing): ?><span class="<?= $prefix ?>-edit-button active"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= e(tr('Modificar datos de la cuenta', 'Edit account details')) ?></span>
        <?php else: ?><a class="<?= $prefix ?>-edit-button" href="<?= e(url('perfil', ['editar' => 1])) ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i><?= e(tr('Modificar datos de la cuenta', 'Edit account details')) ?></a><?php endif ?>
    </div>
    <div class="<?= $prefix ?>-tab"><span><?= e(tr('Datos de la cuenta', 'Account details')) ?></span></div>
    <h2 class="<?= $prefix ?>-section-title"><?= e(tr('Información personal', 'Personal information')) ?></h2>
    <?php if ($editing): ?><form action="api/cuenta.php" method="post" enctype="multipart/form-data" data-password-match="<?= e(tr('Las contraseñas deben coincidir', 'Passwords must match')) ?>"><?= actionFields('guardar') ?><?php endif ?>
        <div class="<?= $prefix ?>-content">
            <div class="<?= $prefix ?>-fields">
                <?php foreach ([['nombre',tr('Nombre','First name'),'text',50,true], ['apellidos',tr('Apellidos','Last name'),'text',75,true], ['email','Email','email',75,true], ['telefono',tr('Teléfono','Phone'),'tel',25,false], ['direccion',tr('Dirección domiciliaria','Home address'),'text',255,false]] as [$field,$label,$type,$max,$required]): ?>
                <div class="<?= $prefix ?>-field"><label for="profile-<?= e($field) ?>"><?= e($label) ?></label><input id="profile-<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" maxlength="<?= $max ?>" value="<?= e($account[$field] ?: ($editing ? '' : '-')) ?>"<?= $editing ? ($required ? ' required' : '') : ' readonly' ?>></div>
                <?php endforeach ?>
                <div class="<?= $prefix ?>-field">
                    <label for="profile-password"><?= e($editing ? tr('Nueva contraseña', 'New password') : tr('Contraseña', 'Password')) ?></label>
                    <input id="profile-password" name="password" type="<?= $editing ? 'password' : 'text' ?>" value="<?= $editing ? '' : '••••••••••••' ?>"<?= $editing ? ' minlength="8" maxlength="72" autocomplete="new-password"' : ' readonly' ?>>
                    <?php if ($editing): ?><span class="<?= $prefix ?>-password-help"><?= e(tr('Deje ambos campos vacíos para conservar la contraseña actual.', 'Leave both fields empty to keep your current password.')) ?></span><?php endif ?>
                </div>
                <div class="<?= $prefix ?>-field <?= $prefix ?>-field-id"><label for="profile-id"><?= e(tr('ID Cuenta','Account ID')) ?></label><input id="profile-id" type="text" value="<?= e($account['id_usuario']) ?>" readonly></div>
                <?php if ($editing): ?><div class="<?= $prefix ?>-field"><label for="profile-confirmacion"><?= e(tr('Confirmar nueva contraseña','Confirm new password')) ?></label><input id="profile-confirmacion" name="confirmacion" type="password" minlength="8" maxlength="72" autocomplete="new-password"></div><?php endif ?>
            </div>
            <div class="<?= $prefix ?>-image-column">
                <div class="<?= $prefix ?>-image-box" data-profile-image-box>
                    <?php if ($avatar): ?><img data-profile-preview src="<?= e($avatar) ?>" alt="<?= e($account['nombre'] . ' ' . $account['apellidos']) ?>"><?php endif ?>
                    <span class="<?= $prefix ?>-image-fallback" data-profile-fallback<?= $avatar ? ' hidden' : '' ?>><i class="fa-solid fa-user" aria-hidden="true"></i></span>
                </div>
                <?php if ($editing): ?><label for="profile-image-input" class="<?= $prefix ?>-image-label"><?= e(tr('Seleccione una imagen','Select an image')) ?></label><input id="profile-image-input" type="file" name="imagen" accept="image/jpeg,image/png,image/webp" hidden><?php endif ?>
            </div>
        </div>
        <?php if ($editing): ?><div class="<?= $prefix ?>-actions"><?= linkTo('perfil', tr('Descartar cambios','Discard changes'), [], $prefix . '-secondary-button') ?><button type="submit" class="<?= $prefix ?>-primary-button"><?= e(tr('Guardar cambios','Save changes')) ?></button></div></form><?php endif ?>
    <?php if ($confirmed): ?><div class="<?= $prefix ?>-confirmation-overlay" role="dialog" aria-modal="true" aria-labelledby="profile-confirmation-title"><div class="<?= $prefix ?>-confirmation-modal"><p id="profile-confirmation-title"><?= e(tr('Los cambios se han aplicado correctamente','Your changes have been applied successfully')) ?></p><i class="fa-solid fa-check <?= $prefix ?>-confirmation-check" aria-hidden="true"></i><?= linkTo('perfil',tr('Regresar','Back'),[],$prefix.'-primary-button') ?></div></div><?php endif ?>
</section>
<?php endif ?>

