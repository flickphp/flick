<nav class="flick-breadcrumbs" aria-label="breadcrumb">
	<ol class="flick-breadcrumbs-list">
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) == $step) { ?>
				<li class="flick-breadcrumb-item flick-breadcrumb-active" aria-current="page">
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } else { ?>
				<li class="flick-breadcrumb-item">
					<?php if (in_array($step, $this->multistepCompletedSteps())) { ?>
						<a href="<?= htmlspecialchars($this->stepUrl($step), ENT_QUOTES, 'UTF-8') ?>">
							<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
						</a>
					<?php } else { ?>
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					<?php } ?>
				</li>
			<?php } ?>
		<?php } ?>
	</ol>
</nav>
