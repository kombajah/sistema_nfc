<?php $act = basename($_SERVER['PHP_SELF']); function lk($f,$t,$a){ return "<a href='$f' class='text-white text-decoration-none small ".($a==$f?'fw-bold':'')."'>$t</a>"; } ?>
<div class="header-app d-flex justify-content-around flex-wrap gap-2 pb-2">
  <?= lk('reporte.php','📊 Reporte',$act) ?>
  <?= lk('nfc.php','🛜 NFC',$act) ?>
  <?= lk('canje.php','🎁 Canje',$act) ?>
  <?= lk('historico.php','🕘 Histórico',$act) ?>
  <?= lk('contenido.php','⚙️ Contenido',$act) ?>
  <?php if (es_admin()): ?><?= lk('maestros.php','🔑 Maestros',$act) ?><?php endif; ?>
  <a href="salir.php" class="text-white text-decoration-none small">Salir</a>
</div>
<div class="text-center text-white small pb-3"><?= h($_SESSION['maestro']) ?> · <?= es_admin()?'Administrador':'Docente' ?></div>
