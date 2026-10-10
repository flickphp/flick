<p><?= htmlspecialchars($reviewText, ENT_QUOTES, 'UTF-8') ?></p>
<table class="table table-striped">
	<tbody>
		<?php foreach ($this->multistepReviewData() as $key => $value) { ?>
			<tr>
				<td><strong><?= htmlspecialchars(ucwords($key), ENT_QUOTES, 'UTF-8') ?></strong></td>
				<td><?= htmlspecialchars(is_array($value) ? implode(', ', $value) : (string) $value, ENT_QUOTES, 'UTF-8') ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
<?= $this->submitMultistep($submitText) ?>
