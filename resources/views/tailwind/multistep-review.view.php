<p class="text-sm text-gray-700 mb-4"><?= htmlspecialchars($reviewText, ENT_QUOTES, 'UTF-8') ?></p>
<table class="min-w-full divide-y divide-gray-200">
	<tbody class="divide-y divide-gray-200">
		<?php foreach ($this->multistepReviewData() as $key => $value) { ?>
			<tr>
				<td class="px-3 py-2 text-sm font-medium text-gray-900"><?= htmlspecialchars(ucwords($key), ENT_QUOTES, 'UTF-8') ?></td>
				<td class="px-3 py-2 text-sm text-gray-700"><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
<?= $this->submitMultistep($submitText) ?>
