<!--  Delete instance  -->
<div class="modal fade" id="deleteInstance" tabindex="-1" role="dialog" aria-labelledby="deleteInstanceLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form method="post" action="<?php echo html( url( 'delete' ) ) ?>">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="deleteInstanceLabel">Delete instance</h4>
					<input type="hidden" class="instance" name="instance[]" value=""/>
				</div>

				<div class="modal-body">
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger delete">Delete</button>
				</div>
			</form>
		</div>
	</div>
</div>


<!--  Delete backup  -->
<div class="modal fade" id="deleteBackup" tabindex="-1" role="dialog" aria-labelledby="deleteBackupLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<form method="post" action="<?php echo html( url( 'manage' ) ) ?>">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<h4 class="modal-title" id="deleteBackupLabel">Delete backup</h4>
					<input type="hidden" class="instance" name="instance[]" value=""/>
					<input type="hidden" class="filename" name="filename" value=""/>
				</div>

				<div class="modal-body">
				</div>

				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
					<button type="button" class="btn btn-danger delete">Delete</button>
				</div>
			</form>
		</div>
	</div>
</div>


<!--  Multi purpose modal  -->
<div class="modal fade" id="trimModal" tabindex="-1" role="dialog" aria-labelledby="trimModalLabel">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title" id="trimModalLabel">Message Log</h4>
			</div>

			<div class="modal-body">
				<pre class="log"></pre>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
			</div>
		</div>
	</div>
</div>
