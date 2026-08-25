<nav class="test-breadcrumbs" aria-label="breadcrumb">
	<ol class="test-breadcrumbs-list">
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) == $step) { ?>
				<li class="test-breadcrumb-item test-breadcrumb-active" aria-current="page">
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } else { ?>
				<li class="test-breadcrumb-item">
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
