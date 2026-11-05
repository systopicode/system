<div class="cmsChoices accountSectionLinks">
<?php foreach ($sections as $key => [$label, $description, $icon]) { ?>
    <a data-account-selected="<?= $key === $area ? '1' : '0' ?>" data-focus-id="account-section-<?= $e($accountNavigationVariant . '-' . $key) ?>" class="cmsChoice ajax<?= $key === $area ? ' selected' : '' ?>" href="<?= $e($this->href . 'showSection?section=' . $key) ?>"<?= $key === $area ? ' aria-current="page"' : '' ?>>
        <i class="cmsIcon cmsIcon-<?= $e($icon) ?>" aria-hidden="true"></i>
        <span><b><?= $e($label) ?></b><small><?= $e($description) ?></small></span>
        <span class="button transparent icononly summaryToggle" aria-hidden="true"><i class="cmsIcon cmsIcon-chevron-right"></i></span>
    </a>
<?php } ?>
</div>
