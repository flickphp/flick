<p><?= htmlspecialchars($reviewText, ENT_QUOTES, 'UTF-8') ?></p>
<table class="table is-striped is-fullwidth">
	<tbody>
		<?php foreach ($this->multistepReviewData() as $key => $value) { ?>
			<tr>
				<td><strong><?= htmlspecialchars(ucwords($key), ENT_QUOTES, 'UTF-8') ?></strong></td>
				<td><?= htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') ?></td>
			</tr>
		<?php } ?>
	</tbody>
</table>
<?= $this->submitMultistep($submitText) ?>
