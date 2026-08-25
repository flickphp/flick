<div class="field">
	<a href="<?= htmlspecialchars($this->stepUrl('submit'), ENT_QUOTES, 'UTF-8') ?>">
		<button type="button" <?= $attributesString !== '' ? $attributesString : 'class="button is-primary"' ?>>
			<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
		</button>
	</a>
</div>
