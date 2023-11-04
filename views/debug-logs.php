<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php echo esc_html__('Debug logs', 'wcfb'); ?>
    </h1>
    <hr class="wp-header-end">
    <br>
    <?php if ($logs) : ?>
    <form method="post" action="<?php echo esc_url($this->getCurrentUrl()) ?>">
        <button  type="submit" name="delete" value="1" class="button button-primary">
            <?php echo esc_html__('Delete old logs', 'wcfb'); ?>
        </button>
    </form>
    <br>
    <?php endif; ?>
<pre>
    <?php if ($logs) :
        print_r($logs);
    else :
        echo esc_html__('Logs not found!', 'wcfb');
    endif; ?>
</pre>
</div>