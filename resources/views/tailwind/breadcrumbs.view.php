<nav class="flex mb-4" aria-label="Breadcrumb">
	<ol class="inline-flex items-center space-x-1 md:space-x-3">
		<?php foreach ($this->multistepSteps($form) as $step) { ?>
			<?php if ($this->multistepCurrentStep($form) == $step) { ?>
				<li class="inline-flex items-center text-sm font-medium text-gray-700" aria-current="page">
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } elseif (in_array($step, $this->multistepCompletedSteps())) { ?>
				<li class="inline-flex items-center">
					<a href="<?= htmlspecialchars($this->stepUrl($step), ENT_QUOTES, 'UTF-8') ?>" class="inline-flex items-center text-sm font-medium text-blue-600 hover:text-blue-800">
						<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
					</a>
				</li>
			<?php } else { ?>
				<li class="inline-flex items-center text-sm font-medium text-gray-400">
					<?= htmlspecialchars($step, ENT_QUOTES, 'UTF-8') ?>
				</li>
			<?php } ?>
		<?php } ?>
	</ol>
</nav>
