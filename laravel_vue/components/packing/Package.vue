<template>
<div :id="'package-' + thePackage.id"
    class="box packing-icon"
    ondragover="return false"
    ondragenter="return false"
    @drop.stop.prevent="addItemToPackage($event, thePackage)"
    draggable="true"
    @dragstart="dragPackage($event, thePackage)"
>
    {{ thePackage.name }}<br>
    {{ thePackage.box.label }}<br>
    {{ thePackage.box.width }}x{{ thePackage.box.height }}x{{ thePackage.box.length }}

    <b-popover :target="'package-' + thePackage.id"
        :show.sync="thePackage.showPopover"
        @show="$root.$emit('bv::hide::popover')"
    >
        <input type="text"
            v-model="thePackage.name"
            class="form-control"
            placeholder="Name"
        >
        <br>
        <div
            v-for="child in thePackage.child_packages"
            :data-package-id="child.id"
            draggable="true"
            @dragstart="dragPackage($event, child)"
        >
            <span class="glyphicon glyphicon-move"></span>
            {{ child.name }} ({{ child.box.label }})<br>
            {{ child.box.width }}x{{ child.box.height }}x{{ child.box.length }}
        </div>
        <div
            v-for="item in thePackage.child_items"
            draggable="true"
            @dragstart="$emit('drag-item', item, thePackage, $event)"
        >
            <span class="glyphicon glyphicon-move"></span>
            {{ item.description }}
        </div>
        <div v-if="thePackage.loading || loading">
            <span class="glyphicon glyphicon-repeat spinning loading"></span>
        </div>
    </b-popover>
</div>
</template>

<script>
var _ = require('lodash');

export default {
    props: ['thePackage'],
    data() { return { loading: false }; },
    computed: {
        packageName() {
            return this.thePackage.name;
        },
    },
    watch: {
        packageName: _.debounce(function() {
            if (this.thePackage.name.length) {
                var url = '/packing/packages/' + this.thePackage.id;
                this.loading = true;

                axios.put(url, { name: this.thePackage.name }).then(response => {
                    this.loading = false;
                    this.$emit('update-package', response.data.thePackage);
                });
            }
        }, 1000),
    },
    methods: {
        /**
         * Add item to package.
         */
        addItemToPackage: function(event, thePackage) {
            var data = JSON.parse(event.dataTransfer.getData('text/plain'));

            if (data.type === 'package') {
                return this.putPackageInPackage(data.thePackage, thePackage);
            }
            if (typeof data.item.id === 'undefined') {
                return;
            }
            Vue.set(thePackage, 'showPopover', true);
            Vue.set(thePackage, 'loading', true);

            axios.put('/packing/item/' + data.item.id + '/package/' + thePackage.id)
                .then(response => {
                    this.$emit('update-item', response.data.item);
                    var thePackage = response.data.thePackage;
                    thePackage.showPopover = true;
                    this.$emit('update-package', thePackage);
                });
        },

        /**
         * Put one package inside another.
         */
        putPackageInPackage: function(childPackage, parentPackage) {
            if (childPackage.id === parentPackage.id) {
                return;
            }
            var data = {
                parent_id: parentPackage.id,
                in_shipping: parentPackage.in_shipping,
            };
            Vue.set(parentPackage, 'showPopover', true);
            Vue.set(parentPackage, 'loading', true);

            axios.put('/packing/packages/' + childPackage.id, data)
                .then(response => {
                    var parentPackage = response.data.thePackage.parent_package;
                    parentPackage.showPopover = true;
                    this.$emit('update-package', response.data.thePackage);
                    this.$emit('update-package', parentPackage);
                });
        },

        /**
         * Store dragged package info in drag event.
         */
        dragPackage(event, thePackage) {
            event.dataTransfer.setData('text/plain', JSON.stringify({
                type: 'package',
                thePackage: thePackage,
            }));
            setTimeout(() => this.$root.$emit('bv::hide::popover'), 0);
        },
    },
}
</script>
