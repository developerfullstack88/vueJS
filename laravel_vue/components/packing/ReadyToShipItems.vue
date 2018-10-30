<template>
<div>
<h4>Shipping Package:</h4>
<div class="shipping-packages clearfix"
    ondragover="return false"
    ondragenter="return false"
    @drop="$emit('drop', $event)"
>
    <package v-for="(thePackage, index) in packages"
        :the-package="thePackage"
        :key="index"
        @update-item="item => $emit('update-item', item)"
        @update-package="thePackage => $emit('update-package', thePackage)"
        @drag-item="(item, thePackage, event) => $emit('drag-item', item, thePackage, event)"
    ></package>
    <span v-if="loading" class="glyphicon glyphicon-repeat spinning loading"></span>
</div>
</div>
</template>
<script>
    export default {
        props: ['packages', 'loading'],
    }
</script>
