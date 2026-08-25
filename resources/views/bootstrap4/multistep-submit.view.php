<div class="mt-3">
	<a href="<?= htmlspecialchars($this->stepUrl('submit'), ENT_QUOTES, 'UTF-8') ?>">
		<button type="button" <?= $attributesString !== '' ? $attributesString : 'class="btn btn-primary"' ?>>
			<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
		</button>
	</a>
</div>
