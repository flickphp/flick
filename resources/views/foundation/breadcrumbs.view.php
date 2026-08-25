<nav aria-label="You are here:" role="navigation">
	<ul class="breadcrumbs">
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) == $step) { ?>
				<li>
					<span class="show-for-sr">Current: </span>
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } else { ?>
				<?php if (in_array($step, $this->multistepCompletedSteps())) { ?>
					<li>
						<a href="<?= htmlspecialchars($this->stepUrl($step), ENT_QUOTES, 'UTF-8') ?>">
							<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
						</a>
					</li>
				<?php } else { ?>
					<li class="disabled">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</li>
				<?php } ?>
			<?php } ?>
		<?php } ?>
	</ul>
</nav>
