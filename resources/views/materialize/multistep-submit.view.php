<div class="section">
	<a href="<?= htmlspecialchars($this->stepUrl('submit'), ENT_QUOTES, 'UTF-8') ?>">
		<button type="button" <?= $attributesString !== '' ? $attributesString : 'class="btn waves-effect waves-light"' ?>>
			<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
		</button>
	</a>
</div>
