<template>
<div class="col-xs-12">
    <ul class="nav nav-tabs">
        <li v-for="tab in tabs" :class="getActive(tab)">
            <a @click="setActive(tab)" href="#">
                {{ tab.charAt(0).toUpperCase() + tab.slice(1) }}
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div v-if="hasTab('item')" :class="'tab-pane ' + getActive('item')" id="right-items">
            <item-manager :initial-items="items"
                @all-fragilities-set="setActive('pack')"
            ></item-manager>
        </div>

        <div v-if="hasTab('pack')" :class="'tab-pane packing-manager ' + getActive('pack')" id="right-pack">
            <packing-manager
                :initial-items="items"
                :packages="packages"
                :add-wrapping-material="addWrappingId"
                :add-package="addPackageId"
                @items-update="updateItems"
                @new-package="addPackage"
                @packages-update="updatePackages"
                @package-update="updatePackage"
                @package-deleted="deletePackage"
                @finished-packing="$emit('finished-packing')"
                @not-finished-packing="$emit('not-finished-packing')"
            ></packing-manager>
        </div>

        <div v-if="hasTab('receipt')" :class="'tab-pane ' + getActive('receipt')" id="right-receipt">
            <packing-receipt
                :items="items"
                :packages="packages"
            ></packing-receipt>
        </div>

        <div v-if="hasTab('shipping')" :class="'tab-pane ' + getActive('shipping')" id="right-shipping">
            <ul class="nav nav-pills">
                <li v-for="thePackage in readyToShipPackages"
                    :class="thePackage.id === activePackage.id ? 'active' : ''"
                >
                    <a href="#" @click="activePackage=thePackage">
                        {{ thePackage.name }}
                    </a>
                </li>
            </ul>
            <shipping-details v-for="thePackage in readyToShipPackages" :key="thePackage.id"
                :class="thePackage.id === activePackage.id ? '' : 'hide'"
                :the-package="thePackage"
                :items="initialItems"
                :initial-customer="initialItems[0].customer || initialItems[0].customers"
                :packing-time-cost="packingTimeCostPerPackage"
                :adjustment="adjustmentPerPackage"
                :locations="locations"
                :app-settings="appSettings"
                :save="saveActivePackage && thePackage.id === activePackage.id"
                :customer="customer"
                @shipping-price-change="price => updatePackageShipping(thePackage, price)"
                @insurance-update="cost => $emit('insurance-update', cost)"
                @saving="data => $emit('saving-package', data)"
                @saved="data => $emit('saved-package', data)"
                @update-customer="newCustomer => customer = newCustomer"
            ></shipping-details>
        </div>

    </div>
</div>
</template>

<script>
export default {
    props: [
        'showOnly',
        'initialItems',
        'initialPackages',
        'packingTime',
        'packingTimeCost',
        'adjustment',
        'hourlyRate',
        'initialShippingPrice',
        'addWrappingId',
        'addPackageId',
        'locations',
        'nextToggle',
        'appSettings',
        'initialTab',
        'saveActivePackage',
    ],
    data() {
        return {
            items: this.initialItems,
            packages: this.initialPackages,
            shippingPrice: this.initialShippingPrice,
            tabs: ['item', 'pack', 'receipt', 'shipping'],
            activeTab: this.initialTab || 'item',
            activePackage: this.initialPackages[0] || 0,
            customer: this.initialItems.length ? this.initialItems[0].customer : {},
        };
    },
    mounted() {
        if (this.showOnly) {
            this.tabs = this.tabs.filter(tab => this.showOnly.indexOf(tab) !== -1);
        }
        this.$emit('box-total-update', this.boxTotal);
        this.$emit('wrappings-total-update', this.wrappingsTotal);
    },
    computed: {
        /**
         * Total cost of boxes.
         */
        boxTotal: function() {
            return this.packages.reduce(function(total, thePackage) {
                return parseFloat((total + thePackage.box.retail_price).toFixed(2));
            }, 0);
        },
        /**
         * Total cost of materials.
         */
        wrappingsTotal: function() {
            var total = 0;
            this.items.forEach(function(item) {
                item.box_inventories.forEach(function(box) {
                    total += box.retail_price;
                });
            });
            return parseFloat(total.toFixed(2));
        },

        /**
         * Ready to ship packages.
         */
        readyToShipPackages() {
            return this.packages.filter(function(thePackage) {
                return thePackage.in_shipping === true
                    && thePackage.parent_id === null;
            });
        },

        /**
         * Packing time cost divided evenly amound number of packages.
         */
        packingTimeCostPerPackage() {
            var totalCost = parseFloat(this.packingTimeCost);
            var packageCount = this.readyToShipPackages.length;

            return (totalCost/packageCount).toFixed(2);
        },

        /**
         * 'Adjustment' divided evenly amound number of packages.
         */
        adjustmentPerPackage() {
            var totalAdjustment = parseFloat(this.adjustment);
            var packageCount = this.readyToShipPackages.length;

            return (totalAdjustment/packageCount).toFixed(2);
        },

        /**
         * Shipping total.
         */
        shippingTotal() {
            return this.packages.reduce((accumulator, thePackage) => {
                return accumulator + parseFloat(thePackage.shipping_cost);
            }, 0);
        },
    },
    watch: {
        nextToggle() {
            this.setActive(this.tabs[this.tabs.indexOf(this.activeTab) + 1]);
        },
        shippingTotal() {
            this.$emit('shipping-update', this.shippingTotal);
        },
        activePackage() {
            this.$emit('active-package-update', this.activePackage);
        },
    },
    methods: {
        /**
         * Replace item array with updated item array.
         */
        updateItems(newItems) {
            this.items = newItems;
            this.$emit('items-update', this.items);
            this.$emit('wrappings-total-update', this.wrappingsTotal);
        },

        /**
         * Replace single item with updated version.
         */
        updateItem(newItem) {
            this.items.forEach((item, index) => {
                if (item.id === newItem.id) {
                    Vue.set(this.items, index, newItem);
                }
            });
            this.$emit('items-update', this.items);
            this.$emit('wrappings-total-update', this.wrappingsTotal);
        },

        /**
         * Add a new package to the packages array.
         */
        addPackage(thePackage) {
            this.packages.push(thePackage);
            this.$emit('box-total-update', this.boxTotal);
        },

        /**
         * Replace packages array with updated packages array.
         */
        updatePackages(newPackages) {
            this.packages = newPackages;
            this.$emit('box-total-update', this.boxTotal);
        },

        /**
         * Replace single package with updated version.
         */
        updatePackage(newPackage) {
            this.packages.forEach((thePackage, index) => {
                if (thePackage.id === newPackage.id) {
                    Vue.set(this.packages, index, newPackage);
                    if (newPackage.in_shipping) {
                        this.activePackage = newPackage;
                    }
                }
            });
        },

        /**
         * Delete package.
         */
        deletePackage(deletedPackage) {
            this.packages.forEach((thePackage, index) => {
                if (thePackage.id === deletedPackage.id) {
                    this.packages.splice(index, 1);
                }
            });
            this.$emit('box-total-update', this.boxTotal);
        },

        /**
         * Update package shipping cost.
         */
        updatePackageShipping(thePackage, shippingCost) {
            thePackage.shipping_cost = shippingCost;
            this.updatePackage(thePackage);
        },

        /**
         * Return 'active' if given tab is active.
         */
        getActive(tab) {
            return this.activeTab === tab ? 'active' : '';
        },

        /**
         * Set the active tab.
         */
        setActive(tab) {
            this.activeTab = tab;
            if (this.activeTab === 'item' || this.activeTab === 'pack') {
                this.$emit('packing-tabs-active');
            } else {
                this.$emit('packing-tabs-not-active');
            }
            this.$emit('active-tab-change', this.activeTab);
        },

        /**
         * Check for tab in tabs.
         */
        hasTab(tab) {
            return this.tabs.indexOf(tab) > -1;
        }
    },
}
</script>
