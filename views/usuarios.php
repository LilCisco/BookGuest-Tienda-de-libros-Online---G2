<?php
// Prepara el listado con sus filtros y muestra el formulario o la confirmación de la cuenta seleccionada.

$term = textInput($_GET, 'q');
$state = textInput($_GET, 'estado');
$roleFilter = textInput($_GET, 'rol');
$selecting = textInput($_GET, 'seleccionar') === '1';
$showFilters = textInput($_GET, 'filtros') === '1';
$allAccounts = sql("SELECT u.id_usuario, u.nombre, u.apellidos, u.email, u.telefono, u.fecha_creacion, u.activo,
    EXISTS (SELECT 1 FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE ur.id_usuario = u.id_usuario AND r.nombre = 'ROLE_ADMIN') AS administrador,
    (SELECT COUNT(*) FROM pedido p WHERE p.id_usuario = u.id_usuario AND p.estado <> 'Cancelado') AS compras
    FROM usuario u WHERE CONCAT(u.nombre, ' ', u.apellidos, ' ', u.email, ' ', COALESCE(u.telefono, '')) LIKE ? ORDER BY u.id_usuario", ['%' . $term . '%'])->fetchAll();
$allAccounts = array_values(array_filter($allAccounts, static fn ($item) =>
    ($state === '' || ($state === 'activo' ? $item['activo'] : !$item['activo'])) &&
    ($roleFilter === '' || ($roleFilter === 'admin' ? $item['administrador'] : !$item['administrador']))));
$last = max(1, (int) ceil(count($allAccounts) / 9));
$current = min($last, max(1, (int) textInput($_GET, 'pagina')));
$accounts = array_slice($allAccounts, ($current - 1) * 9, 9);
$params = ['q' => $term, 'estado' => $state, 'rol' => $roleFilter, 'seleccionar' => $selecting ? 1 : 0];
$summary = row("SELECT COUNT(*) AS registrados, COALESCE(SUM(activo = 1), 0) AS activos,
    COALESCE(SUM(fecha_creacion >= CURRENT_DATE - INTERVAL 6 DAY), 0) AS recientes,
    (SELECT COUNT(*) FROM usuario_rol ur JOIN rol r USING (id_rol) WHERE r.nombre = 'ROLE_ADMIN') AS administradores FROM usuario");
$confirmation = $_SESSION['admin_confirmation'] ?? null;
$confirmed = $page === 'usuarios' && ($confirmation['kind'] ?? '') === 'usuario';
?>
<section class="admin-card admin-summary-card">
    <h2 class="admin-card-title"><?= e(tr('Resumen de usuarios', 'User summary')) ?></h2>
    <div class="users-summary">
        <div class="users-summary-item"><div class="users-summary-title"><?= e(tr('Usuarios registrados', 'Registered users')) ?></div><div class="users-summary-value"><?= e($summary['registrados']) ?></div><div class="users-summary-caption"><?= e($summary['recientes']) ?> <?= e(tr('en los últimos 7 días', 'in the last 7 days')) ?></div></div>
        <div class="users-summary-item"><div class="users-summary-title orange"><?= e(tr('Cantidad de usuarios activos', 'Active users')) ?></div><div class="users-summary-value"><?= e($summary['activos']) ?></div><div class="users-summary-caption"><?= e(tr('Cuentas habilitadas', 'Enabled accounts')) ?></div></div>
        <div class="users-summary-item"><div class="users-summary-title purple"><?= e(tr('Administradores', 'Administrators')) ?></div><div class="users-summary-value"><?= e($summary['administradores']) ?></div><div class="users-summary-caption"><?= e(tr('Con acceso al panel administrativo', 'With access to the administration panel')) ?></div></div>
    </div>
</section>
<section class="admin-card admin-list-card">
    <div class="users-card-header"><h2 class="admin-card-title"><?= e(tr('Listado de usuarios', 'User list')) ?></h2><div class="users-actions">
        <?php if ($selecting): ?><?= linkTo('usuarios', tr('Cancelar acción','Cancel action'), [], 'users-btn-primary') ?><?php else: ?><?= linkTo('editar-usuario', tr('Añadir usuario','Add user'), [], 'users-btn-primary') ?><?php endif ?>
        <a href="<?= e(url('usuarios', ['filtros'=>$showFilters ? 0 : 1,'pagina'=>$current] + $params)) ?>" class="users-btn-secondary"><i class="fa-solid fa-filter" aria-hidden="true"></i><?= e(tr('Filtros','Filters')) ?></a>
        <?php if ($selecting): ?><button type="button" class="users-btn-secondary" data-edit-selection="usuario" data-edit-url="<?= e(url('editar-usuario')) ?>" data-selection-message="<?= e(tr('Debe seleccionar un usuario para modificar.','Select a user to modify.')) ?>"><i class="fa-solid fa-pencil" aria-hidden="true"></i><?= e(tr('Modificar usuario','Modify user')) ?></button>
        <?php else: ?><a href="<?= e(url('usuarios', ['seleccionar'=>1,'pagina'=>$current] + $params)) ?>" class="users-btn-secondary"><i class="fa-solid fa-pencil" aria-hidden="true"></i><?= e(tr('Modificar usuario','Modify user')) ?></a><?php endif ?>
    </div></div>
    <?php if ($showFilters): ?><div class="users-filter-box"><form action="index.php" method="get" class="admin-filter-form">
        <?= hidden('p','usuarios') . hidden('filtros',1) . hidden('seleccionar',$selecting ? 1 : 0) ?>
        <div class="users-filter-query"><label for="users-query" class="filter-label"><?= e(tr('Buscar usuario','Search user')) ?></label><input id="users-query" type="text" name="q" class="text-control" value="<?= e($term) ?>" maxlength="150" placeholder="<?= e(tr('Nombre, apellidos, correo o teléfono','Name, surname, email or phone')) ?>"></div>
        <div class="users-filter-choice"><label for="users-state" class="filter-label"><?= e(tr('Estado','Status')) ?></label><select id="users-state" name="estado" class="text-control filter-select"><?php foreach ([''=>tr('Todos','All'),'activo'=>tr('Activo','Active'),'inactivo'=>tr('Inactivo','Inactive')] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $state === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
        <div class="users-filter-choice"><label for="users-role" class="filter-label"><?= e(tr('Rol','Role')) ?></label><select id="users-role" name="rol" class="text-control filter-select"><?php foreach ([''=>tr('Todos','All'),'cliente'=>tr('Cliente','Customer'),'admin'=>tr('Administrador','Administrator')] as $value=>$label): ?><option value="<?= e($value) ?>"<?= $roleFilter === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div>
        <div class="users-filter-action"><button class="users-btn-primary"><?= e(tr('Aplicar filtros','Apply filters')) ?></button></div>
    </form></div><?php endif ?>
    <div class="table-scroll"><table class="users-table"><thead><tr>
        <?php if ($selecting): ?><th><span class="visually-hidden"><?= e(tr('Seleccionar','Select')) ?></span></th><?php endif ?>
        <?php foreach (['ID',tr('Nombre','Name'),tr('Correo','Email'),tr('Teléfono','Phone'),tr('Rol','Role'),tr('Total de compras','Total purchases'),tr('Fecha de registro','Registration date'),tr('Estado','Status')] as $label): ?><th><?= e($label) ?></th><?php endforeach ?>
    </tr></thead><tbody><?php foreach ($accounts as $item): ?><tr>
        <?php if ($selecting): ?><td><input type="checkbox" data-selection="usuario" value="<?= e($item['id_usuario']) ?>" aria-label="<?= e(tr('Seleccionar ','Select ') . $item['nombre'] . ' ' . $item['apellidos']) ?>"></td><?php endif ?>
        <td><?= e($item['id_usuario']) ?></td><td><?= e($item['nombre'] . ' ' . $item['apellidos']) ?></td><td><?= e($item['email']) ?></td><td><?= e($item['telefono'] ?? '-') ?></td><td><span class="users-role<?= $item['administrador'] ? ' admin' : '' ?>"><?= e($item['administrador'] ? tr('Administrador','Administrator') : tr('Cliente','Customer')) ?></span></td><td><?= e($item['compras']) ?></td><td><?= e($item['fecha_creacion'] ? substr($item['fecha_creacion'],0,10) : '-') ?></td><td><span class="users-status-<?= $item['activo'] ? 'active' : 'inactive' ?>"><?= e($item['activo'] ? tr('Activo','Active') : tr('Inactivo','Inactive')) ?></span></td>
    </tr><?php endforeach ?></tbody></table><?php if (!$accounts): ?><div class="users-empty"><?= e(tr('No se encontraron usuarios con los criterios indicados.','No users matched the selected criteria.')) ?></div><?php endif ?></div>
    <nav class="users-pagination" aria-label="<?= e(tr('Paginación','Pagination')) ?>">
        <a class="users-btn-secondary<?= $current <= 1 ? ' disabled' : '' ?>" href="<?= e(url('usuarios',['pagina'=>max(1,$current-1)] + $params)) ?>"<?= $current <= 1 ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= e(tr('Anterior','Previous')) ?></a>
        <span class="users-page-info"><?= e(tr('Página','Page')) ?> <?= $current ?> <?= e(tr('de','of')) ?> <?= $last ?></span>
        <a class="users-btn-secondary<?= $current >= $last ? ' disabled' : '' ?>" href="<?= e(url('usuarios',['pagina'=>min($last,$current+1)] + $params)) ?>"<?= $current >= $last ? ' aria-disabled="true" tabindex="-1"' : '' ?>><?= e(tr('Siguiente','Next')) ?></a>
    </nav>
</section>
<?php if ($page === 'editar-usuario' || $confirmed):
    $id = $confirmed ? (int)$confirmation['id'] : (isset($_GET['id']) ? integer($_GET,'id',0) : 0);
    $target = $id ? findUser($id) : [];
    if ($target === null) { throw new AppError(tr('Usuario inexistente.','User not found.'),404); }
    if ($confirmed) { unset($_SESSION['admin_confirmation']); }
    $modalTitle = $confirmed ? (($confirmation['created'] ?? false) ? tr('El usuario se ha creado correctamente','User created successfully') : tr('Usuario modificado correctamente','User updated successfully')) : ($id ? tr('Modificar usuario','Modify user') : tr('Nuevo usuario','New user'));
    $image = imageUrl($target['ruta_imagen'] ?? null);
?>
<section class="users-modal-layer" role="dialog" aria-modal="true" aria-labelledby="users-modal-title" data-modal-layer>
    <form action="api/admin.php" method="post" enctype="multipart/form-data" class="users-modal<?= $confirmed ? ' confirmation' : ' bookguest-users-form' ?>" data-admin-form>
        <?php if (!$confirmed): ?><?= actionFields('usuario',['id'=>$id,'activo'=>$target['activo'] ?? 1,'direccion'=>$target['direccion'] ?? '']) ?><?php endif ?>
        <h2 id="users-modal-title" class="users-modal-title"><?= e($modalTitle) ?></h2>
        <?php if (!$confirmed && $notice && $notice[1] === 'error'): ?><div class="users-error" role="alert"><?= e($notice[0]) ?></div><?php $notice=null; endif ?>
        <?php if (!$confirmed): ?><div class="users-client-error" data-form-error role="alert"></div><?php endif ?>
        <div class="users-upload-row"><div class="users-image-preview" id="users-preview"><?php if ($image): ?><img src="<?= e($image) ?>" alt="<?= e($target['nombre'] . ' ' . $target['apellidos']) ?>"><?php else: ?><i class="fa-regular fa-user" aria-hidden="true"></i><?php endif ?></div>
            <?php if ($confirmed): ?><span class="users-image-link"><?= e($image ? tr('Imagen guardada correctamente','Image saved successfully') : tr('No se ha seleccionado ninguna imagen','No image was selected')) ?></span><i class="fa-solid fa-check users-confirm-check" aria-hidden="true"></i>
            <?php else: ?><label for="users-image" class="users-image-link"><?= e($id ? tr('Cambiar imagen (opcional)','Change image (optional)') : tr('Seleccione una imagen (opcional)','Select an image (optional)')) ?></label><input type="file" name="imagen" id="users-image" class="users-hidden-file" accept="image/jpeg,image/png,image/webp" data-image-preview="users-preview"><?php endif ?>
        </div>
        <?php foreach ([['nombre',tr('Nombre','Name'),'text',50,tr('Ingresa tu nombre','Enter your first name'),true],['apellidos',tr('Apellidos','Last name'),'text',75,tr('Ingresa tus apellidos','Enter your last name'),true],['email',tr('Correo','Email'),'email',75,tr('Ingresa tu correo electrónico','Enter your email address'),true],['telefono',tr('Teléfono','Phone'),'text',25,tr('Ingresa tu número telefónico (opcional)','Enter your phone number (optional)'),false]] as [$name,$label,$type,$length,$placeholder,$required]): ?>
            <div class="users-form-group"><label for="user-<?= e($name) ?>"><?= e($label) ?><?php if (!$confirmed && $required): ?> <span class="users-required">*</span><?php endif ?></label><input id="user-<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" maxlength="<?= $length ?>" value="<?= e($target[$name] ?? '') ?>"<?= !$id ? ' placeholder="' . e($placeholder) . '"' : '' ?><?= $confirmed ? ' readonly' : ($required ? ' required' : '') ?>></div>
        <?php endforeach ?>
        <div class="users-form-group"><label for="user-rol"><?= e(tr('Rol','Role')) ?><?php if (!$confirmed): ?> <span class="users-required">*</span><?php endif ?></label>
            <?php if ($confirmed): ?><input id="user-rol" value="<?= e(($target['rol'] ?? '') === ADMIN ? tr('Administrador','Administrator') : tr('Cliente','Customer')) ?>" readonly>
            <?php else: ?><select id="user-rol" name="rol" required><option value="<?= CLIENT ?>"><?= e(tr('Cliente','Customer')) ?></option><option value="<?= ADMIN ?>"<?= ($target['rol'] ?? '') === ADMIN ? ' selected' : '' ?>><?= e(tr('Administrador','Administrator')) ?></option></select><?php endif ?>
        </div>
        <?php if ($confirmed): ?><div class="users-form-group"><label for="user-password"><?= e(tr('Contraseña','Password')) ?></label><input id="user-password" value="••••••••" readonly></div>
        <?php else: ?>
            <div class="users-form-group"><label for="user-password"><?= e($id ? tr('Nueva contraseña','New password') : tr('Contraseña','Password')) ?><?php if (!$id): ?> <span class="users-required">*</span><?php endif ?></label><input id="user-password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" placeholder="<?= e($id ? tr('Dejar en blanco para conservarla','Leave blank to keep it') : tr('Crea una contraseña segura','Create a secure password')) ?>"<?= !$id ? ' required' : '' ?>><div class="users-field-help"><?= e($id ? tr('Complete ambos campos solamente si desea cambiarla.','Complete both fields only if you want to change it.') : tr('Debe contener 8 caracteres como mínimo','Must contain at least 8 characters')) ?></div></div>
            <div class="users-form-group"><label for="user-confirmation"><?= e(tr('Confirmar contraseña','Confirm password')) ?><?php if (!$id): ?> <span class="users-required">*</span><?php endif ?></label><input id="user-confirmation" type="password" name="confirmacion" minlength="8" maxlength="72" autocomplete="new-password" placeholder="<?= e(tr('Repite la contraseña','Repeat the password')) ?>"<?= !$id ? ' required' : '' ?>><?php if (!$id): ?><div class="users-field-help"><?= e(tr('Las contraseñas deben coincidir','Passwords must match')) ?></div><?php endif ?></div>
        <?php endif ?>
        <div class="users-modal-actions"><?php if ($confirmed): ?><?= linkTo('usuarios',tr('Regresar','Back'),[],'users-btn-primary') ?><?php else: ?><?= linkTo('usuarios',$id ? tr('Cancelar','Cancel') : tr('Regresar','Back'),$id ? ['seleccionar'=>1] : [],'users-btn-secondary') ?><button class="users-btn-primary"><?= e($id ? tr('Confirmar cambios','Confirm changes') : tr('Crear usuario','Create user')) ?></button><?php endif ?></div>
    </form>
</section>
<?php endif ?>
