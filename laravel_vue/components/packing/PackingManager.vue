<template>
<div>
    <wrapped-items
        :items="wrappedItems"
        :loading="wrappedLoading"
        @drag-item="dragItem"
        @update-item="updateItem"
        @drop.prevent="moveDropedItemToWrapped"
    ></wrapped-items>

    <boxed-items
        :packages="openPackages"
        :loading="boxedLoading"
        @drag-item="dragItem"
        @update-item="updateItem"
        @update-package="updatePackage"
        @drop.prevent="moveToBoxed"
    ></boxed-items>

    <ready-to-ship-items
        :packages="readyToShipPackages"
        :loading="shippingLoading"
        @drag-item="dragItem"
        @drop.prevent="moveToShipping"
        @update-item="updateItem"
        @update-package="updatePackage"
    ></ready-to-ship-items>

    <h3 class="glyphicon glyphicon-trash pull-right alert alert-danger"
        ondragover="return false"
        ondragenter="return false"
        @drop.prevent="remove"
        title="Drop here to delete"
    >
    </h3>
</div>
</template>

<script>
export default {
    props: [
        'initialItems',
        'packages',
        'addWrappingMaterial',
        'addPackage',
    ],
    data() {
        return {
            items: this.initialItems,
            wrappedLoading: false,
            boxedLoading: false,
            shippingLoading: false,
        };
    },
    mounted() {
        this.checkIfFinishedPacking();
    },
    watch: {
        addWrappingMaterial(materialId) {
            if (materialId === 0) {
                return;
            }
            var item = this.wrappedItems.filter(item => item.selected)[0];
            if (typeof item === 'undefined') {
                item = this.wrappedItems[0];
                item.selected = true;
            }
            Vue.set(item, 'showPopover', true);
            Vue.set(item, 'loadingMaterial', true);

            // save wrapping to item
            var component = this;
            axios.put('/packing/box/' + materialId + '/item/' + item.id)
                .then(function(response) {
                    var newItem = response.data;
                    newItem.showPopover = true;
                    newItem.selected = true;
                    component.updateItem(newItem);
                });
        },

        /**
         * Add new package/box.
         */
        addPackage(boxId) {
            if (boxId === 0) {
                return;
            }
            this.boxedLoading = true;
            // need to associate the new empty package with this group of items by
            // passing in one of the item ids.
            var itemId = this.items.slice(-1)[0].id;
            var packageInfo = {
                box_inventory_id: boxId,
                item_id: itemId,
                name: this.getNextBoxName(),
            };
            this.$root.$emit('bv::hide::popover');
            axios.post('/packing/packages', packageInfo)
                .then(response => {
                    this.$emit('new-package', response.data);
                    this.boxedLoading = false;
                });
        },

        items(items) {
            this.checkIfFinishedPacking();
        },
    },
    computed: {
        wrappedItems() {
            return this.items.filter(function(item) {
                return item.packing_stage === 'wrapped' || item.packing_stage === null;
            });
        },
        /**
         * Only show items in the wrapping area if all items have been checked.
         */
        wrappedItemsCheckedOff() {
            return this.allItemsHaveBeenChecked() ? this.wrappedItems : [];
        },
        openPackages() {
            return this.packages.filter(function(thePackage) {
                return thePackage.in_shipping === false
                    && thePackage.parent_id === null;
            });
        },
        readyToShipPackages() {
            return this.packages.filter(function(thePackage) {
                return thePackage.in_shipping === true
                    && thePackage.parent_id === null;
            });
        },
    },
    methods: {
        /**
         * Start item drag.
         */
        dragItem(item, from, event) {
            event.dataTransfer.setData('text/plain', JSON.stringify({
                type: 'item',
                item: item,
                from: from,
            }));
            if (event.dataTransfer.setDragImage) {
                // fixes incorrect drag image position in firefox
                event.dataTransfer.setDragImage(event.target, 10, 10);
            }
            setTimeout(() => this.$root.$emit('bv::hide::popover'), 0);
        },

        moveDropedItemToWrapped(event) {
            var data = JSON.parse(event.dataTransfer.getData('text/plain'));
            if (data.type !== 'item') {
                return;
            }
            this.moveItemToStage(data.item.id, 'wrapped');

            if (typeof data.from.items === 'undefined') {
                return;
            }
            // if item moved from package, delete it from that package's items
            var changedPackage = this.packages.find(
                thePackage => thePackage.id === data.from.id
            );
            if ( ! changedPackage) {
                return;
            }
            changedPackage.child_items.forEach((item, index) => {
                if (item.id === data.item.id) {
                    changedPackage.child_items.splice(index, 1);
                    this.$emit('package-update', changedPackage);
                }
            });
        },

        moveItemToStage(itemId, stage) {
            this.wrappedLoading = true;

            var component = this;
            // update item on server
            axios.put('/packing/item/' + itemId + '/stage/' + stage)
                .then(function(response) {
                    component.wrappedLoading = false;
                    // update component's copy of item
                    component.updateItem(response.data.item);
                    if (response.data.thePackage) {
                        component.updatePackage(response.data.thePackage);
                    }
                });
        },

        /**
         * Replace an item with an updated version.
         */
        updateItem(updatedItem) {
            var component = this;
            component.items.forEach(function(item, index) {
                if (item.id === updatedItem.id) {
                    Vue.set(component.items, index, updatedItem);
                }
            });
            this.$emit('items-update', this.items);
        },
        /**
         * Trigger updates for a package and its contents.
         */
        updatePackage(updatedPackage) {
            updatedPackage.items.forEach(item => this.updateItem(item));
            var childPackages = updatedPackage.child_packages || [];
            childPackages.forEach(thePackage => {
                thePackage.items.forEach(item => this.updateItem(item));
            });
            this.$emit('package-update', updatedPackage);
        },

        /**
         * Move package to boxed area.
         */
        moveToBoxed(event) {
            var data = JSON.parse(event.dataTransfer.getData('text/plain'));
            if (data.type !== 'package') {
                return;
            }
            this.boxedLoading = true;
            this.putPackage(data.thePackage, {
                name: this.getNextBoxName(),
                in_shipping: 0,
                parent_id: '',
            });
        },

        /**
         * Move package to shipping area.
         */
        moveToShipping(event) {
            var data = JSON.parse(event.dataTransfer.getData('text/plain'));
            if (data.type !== 'package') {
                return;
            }
            this.shippingLoading = true;
            this.putPackage(data.thePackage, {
                name: this.getNextPackageName(),
                in_shipping: 1,
                parent_id: '',
            });
        },

        /**
         * Update package on server.
         */
        putPackage(thePackage, data) {
            if (thePackage.parent_id !== null && data.parent_id === '') {
                this.removeChildPackage(thePackage);
            }
            var url = '/packing/packages/' + thePackage.id;

            axios.put(url, data).then(response => {
                // turn off any loading icons
                this.boxedLoading = false;
                this.shippingLoading = false;
                // update package
                this.updatePackage(response.data.thePackage);
            });
        },

        /**
         * Remove a package form its parent.
         */
        removeChildPackage(child) {
            var component = this;
            component.packages.forEach(function(thePackage) {
                if (thePackage.id === child.parent_id) {
                    thePackage.child_packages.forEach(function(childPackage, index) {
                        thePackage.child_packages.splice(index, 1);
                    });
                }
            });
        },
        /**
         * Move item back to unpacked section, or delete package.
         */
        remove(event) {
            var data = JSON.parse(event.dataTransfer.getData('text/plain'));
            switch (data.type) {
                case 'item':
                    this.moveItemToStage(data.item.id, 'wrapped');
                    break;
                case 'package':
                    if (data.thePackage.in_shipping) {
                        this.shippingLoading = true;
                    } else {
                        this.boxedLoading = true;
                    }
                    this.deletePackage(data.thePackage.id);
                    break;
            }
        },

        /**
         * Delete package.
         */
        deletePackage(packageId) {
            axios.delete('/packing/packages/' + packageId).then(response => {
                // move package items back to wrapped
                response.data.items.forEach(item => {
                    item.packing_stage = 'wrapped';
                    item.package_id = null;
                    this.updateItem(item);
                });
                // update child packages
                response.data.child_packages.forEach(thePackage => {
                    thePackage.parent_id = null;
                    this.updatePackage(thePackage);
                });
                this.$emit('package-deleted', response.data);
                this.shippingLoading = false;
                this.boxedLoading = false;
            });
        },
        /**
         * Return true if all items have checked_at_packing = 1.
         */
        allItemsHaveBeenChecked() {
            var itemsChecked = this.items.filter(item => item.checked_at_packing === 1);
            return itemsChecked.length === this.items.length;
        },

        // if all items in shipping, fire finished-packing event
        checkIfFinishedPacking() {
            if (this.items.filter(item => item.packing_stage !== 'shipping').length === 0) {
                this.$emit('finished-packing');
            } else {
                this.$emit('not-finished-packing');
            }
        },

        /**
         * Get next box name.
         */
        getNextBoxName() {
            for (var i = 1; i <= this.packages.length+1; i++) {
                if (this.packages.filter(box => box.name === 'Box ' + i).length === 0) {
                    return 'Box ' + i;
                }
            }
            return 'Box 1';
        },

        /**
         * Get next package name.
         */
        getNextPackageName() {
            for (var i = 1; i <= this.packages.length+1; i++) {
                if (this.packages.filter(box => box.name === 'Package ' + i).length === 0) {
                    return 'Package ' + i;
                }
            }
            return 'Package 1';
        },
    },
}
</script>
