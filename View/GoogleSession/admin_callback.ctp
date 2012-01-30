<?php $this->layout = '' ?>
<script>
	if (window.opener) {
		window.opener.location = '<?php echo $redirect ?>';
		window.close();
	} else {
		window.location = '<?php echo $redirect ?>';
	}
</script>