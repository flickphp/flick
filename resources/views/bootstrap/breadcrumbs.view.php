<nav aria-label="breadcrumb">
	<ol class="breadcrumb">
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) == $step) { ?>
				<li class="breadcrumb-item active" aria-current="page">
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } else { ?>
				<li class="breadcrumb-item">
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
