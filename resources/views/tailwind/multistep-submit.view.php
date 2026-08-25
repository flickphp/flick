<div class="mt-4">
	<a href="<?= htmlspecialchars($this->stepUrl('submit'), ENT_QUOTES, 'UTF-8') ?>">
		<button type="button" <?= $attributesString !== '' ? $attributesString : 'class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"' ?>>
			<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
		</button>
	</a>
</div>
