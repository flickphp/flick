<nav class="breadcrumb" aria-label="breadcrumbs">
	<ul>
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) === $step) { ?>
				<li class="is-active">
					<span aria-current="page" style="padding: 0 0.75em">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</span>
				</li>
			<?php } else { ?>
				<li>
					<?php if (in_array($step, $this->multistepCompletedSteps())) { ?>
						<a href="<?= htmlspecialchars($this->stepUrl($step), ENT_QUOTES, 'UTF-8') ?>">
							<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
						</a>
					<?php } else { ?>
						<span style="padding: 0 0.75em">
							<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
						</span>
					<?php } ?>
				</li>
			<?php } ?>
		<?php } ?>
	</ul>
</nav>
