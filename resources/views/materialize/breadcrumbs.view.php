<nav>
	<div class="nav-wrapper">
		<?php // .col only gets its gutter inside a .row, so pad the bar explicitly?>
		<div class="col s12" style="padding: 0 0.75rem">
			<?php foreach ($this->multistepSteps($form) as $step) { ?>
				<?php if ($this->multistepCurrentStep($form) == $step) { ?>
					<span class="breadcrumb" aria-current="page">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</span>
				<?php } elseif (in_array($step, $this->multistepCompletedSteps())) { ?>
					<a class="breadcrumb" href="<?= htmlspecialchars($this->stepUrl($step), ENT_QUOTES, 'UTF-8') ?>">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</a>
				<?php } else { ?>
					<span class="breadcrumb">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</span>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
</nav>
