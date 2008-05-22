<?php

if( $_SERVER['REQUEST_METHOD'] == 'POST' )
{
	if( isset( $_POST['instance'] ) && is_array( $_POST['instance'] ) )
	{
		foreach( $_POST['instance'] as $id )
		{
			if( $instance = Instance::getInstance( (int) $id ) )
			{
				$instance->delete();
			}
		}
	}

	header( 'Location: ' . url( '' ) );
}

?>
