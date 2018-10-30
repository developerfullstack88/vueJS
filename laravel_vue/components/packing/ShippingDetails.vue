<template>
<div class="shipping-details">
    <input type="hidden" :name="name('wrapping_cost')" :value="wrappingCost">
    <input type="hidden" :name="name('box_cost')" :value="thePackage.box.retail_price">
    <input type="hidden" :name="name('packing_cost')" :value="packingTimeCost">
    <input type="hidden" name="shipping_price" :value="shippingPrice">
    <input type="hidden" :name="name('shipping_cost')" :value="shippingPrice">
    <input type="hidden" :name="name('adjustment')" :value="adjustment">
    <input type="hidden" :name="name('total_cost')" :value="totalCost">
    <input type="hidden" v-if="shipment" :name="name('shipment')" :value="JSON.stringify(shipment)">
    <input type="hidden" v-if="shipment" :name="name('selected_rate')" :value="selectedRateJson">

    <package-dimensions
        :the-package="thePackage"
        :app-settings="appSettings"
        @dimensions-update="newDimensions => dimensions = newDimensions"
        @length-system-update="system => lengthSystem = system"
        @weight-system-update="system => weightSystem = system"
        @errors-update="errors => hasDimensionErrors = errors.length"
    ></package-dimensions>

    <div class="row">
        <div class="col-sm-2">
            <div class="form-group">
                <button type="button"
                    v-if=" ! loadingRates"
                    :disabled="fromAddressStatus !== 'valid' || toAddressStatus !== 'valid'"
                    @click="getRates"
                    class="btn btn-primary"
                >Get Rates</button>
                <button type="button"
                    v-if="loadingRates"
                    disabled
                    class="btn btn-primary"
                >
                    Loading
                    <span class="glyphicon glyphicon-repeat spinning loading"></span>
                </button>
            </div>
        </div>
        <div class="col-sm-4">
            <b>Origin:</b>
            <span v-if="fromAddressStatus==='checking'"
                class="glyphicon glyphicon-repeat spinning"
            ></span>
            <span v-if="fromAddressStatus==='valid'"
                class="glyphicon glyphicon-ok text-success"
            ></span>
            <span v-if="fromAddressStatus==='invalid'"
                class="glyphicon glyphicon-warning-sign text-danger"
            ></span>
            <address>
                <div>{{ appSettings.business_address }}</div>
                <div>{{ appSettings.address2 }}</div>
                <div>{{ appSettings.city }}, {{ appSettings.state }} {{ appSettings.zip }}</div>
                <div>{{ appSettings.business_phone }}</div>
            </address>
            <div v-if="fromAddressErrors.length"
                :class="'alert alert-' + (fromAddressStatus === 'invalid' ? 'danger' : 'warning')"
            >
                <div v-for="error in fromAddressErrors">
                    <b>{{ error.field }}:</b> {{ error.message }}
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <b>Destination:</b>
            <span v-if="toAddressStatus==='checking'"
                class="glyphicon glyphicon-repeat spinning"
            ></span>
            <span v-if="toAddressStatus==='valid'"
                class="glyphicon glyphicon-ok text-success"
            ></span>
            <span v-if="toAddressStatus==='invalid'"
                class="glyphicon glyphicon-warning-sign text-danger"
            ></span>
            <address v-if="customer">
                <div :id="name('street')" tabindex="0" :class="toClass('street')" ref="street">
                    {{ customer.street }}
                </div>
                <div>
                    <span :id="name('city')" tabindex="0" :class="toClass('city')" ref="city">
                        {{ customer.city }}
                    </span>,
                    <span :id="name('state')" tabindex="0" :class="toClass('state')" ref="state">
                        {{ customer.state }}
                    </span>
                    <span :id="name('zip')" tabindex="0" :class="toClass('zip')" ref="zip">
                        {{ customer.zip }}
                    </span>
                </div>
                <b-popover v-for="(correction, field) in toAddressCorrections"
                    :key="field"
                    :target="name(field)"
                    triggers="focus"
                    title="Save Correction?"
                >
                    {{ correction }}
                    <button @click="saveCorrection(field)"
                        type="button"
                        class="btn btn-xs btn-success"
                        :disabled="toAddressStatuses[field] === 'saving'"
                    >Save</button>
                    <span v-if="toAddressStatuses[field] === 'saving'"
                        class="glyphicon glyphicon-repeat spinning"
                    ></span>
                </b-popover>
                <div>{{ customer.mobile_number || customer.home_number || customer.work_number }}</div>
            </address>
            <div v-if="toAddressErrors.length"
                :class="'alert alert-' + (toAddressStatus === 'invalid' ? 'danger' : 'warning')"
            >
                <div v-for="error in toAddressErrors">
                    <b>{{ error.field }}:</b> {{ error.message }}
                </div>
            </div>
        </div>
    </div>

    <div v-if="shipment && shipment.messages.length" class="alert alert-warning">
        <div v-for="message in shipment.messages">{{ message.carrier }}: {{ message.message }}</div>
    </div>

    <div class="form-group">
        <ul class="list-group" id="shipping-rates">
            <li v-for="rate in rates"
                :class="'list-group-item ' + (rate.id === selectedRate ? 'active' : '')"
                :id="rate.id"
            >
                <label class="text-center">
                    <input type="radio"
                        :name="name('rate_id')"
                        :value="rate.id"
                        v-model="selectedRate"
                        @change="shippingPrice=rate.charged_rate"
                        class="hide"
                    >
                    <h5 class="uc-words">
                        {{ rate.carrier }}
                        {{ rate.service.replace(/_/g, ' ').toLowerCase() }}
                    </h5>
                    <h2>${{ parseFloat(rate.charged_rate).toFixed(2) }}</h2>
                    <div class="nowrap">
                        {{ rate.encoded_rate }}
                        {{ rate.encoded_list }}
                    </div>
                </label>
            </li>
        </ul>
    </div>

    <div class="row">
        <div id="insurance_price-group" class="form-group col-xs-5">
            <label for="insurance_price">Insurance Value:</label>
            <div class="input-group">
                <span class="input-group-addon">$</span>
                <input type="number"
                    :name="name('insurance_value')"
                    v-model.number="insurancePrice"
                    id="insurance_price"
                    step="1"
                    placeholder="00.00"
                    class="form-control allow_numeric"
                >
            </div>
            <div class="help-block" id="insurance_price_error"></div>
        </div>
        <div class="col-xs-7 form-group" v-if="insurancePrice > 0">
            <label>Insurance Cost:</label>
            <p class="form-control-static">${{ insuranceCost }}</p>
        </div>
        <input type="hidden" :name="name('insurance_cost')" :value="insuranceCost">
    </div>

    <div id="location-group" class="form-group">
        <label for="location">Location</label>
        <select id="location" name='location' class="form-control" v-model="selectedLocationId">
            <option value="0">Select Location</option>
            <option v-for="location in locations" :value="location.id">{{ location.location }}</option>
        </select>
        <div class="help-block" id="location_error"></div>
    </div>

    <div class="form-group">
        <label for="notes">Notes</label>
        <textarea name="notes" rows="2" class="form-control" id="notes" v-model="notes"></textarea>
    </div>

</div>
</template>

<script>
var _ = require('lodash');

export default {
    props: [
        'thePackage',
        'initialCustomer',
        'items',
        'locations',
        'appSettings',
        'packingTimeCost',
        'adjustment',
        'save',
    ],
    data() {
        return {
            customer: this.initialCustomer,
            dimensions: {},
            hasDimensionErrors: true,
            shippingPrice: 0,
            insurancePrice: Number,
            shippingOption: 0,
            selectedLocationId: 0,
            notes: '',
            rates: [],
            loadingRates: false,
            hasRates: false,
            selectedRate: null,
            shipment: null,
            loadingPostageLabel: false,
            shipmentPurchased: false,
            purchaseError: null,
            fromAddressStatus: 'checking',
            toAddressStatus: 'checking',
            fromAddressErrors: [],
            toAddressErrors: [],
            toAddressCorrections: {},
            toAddressStatuses: {},
            lengthSystem: this.appSettings.units_system,
            weightSystem: this.appSettings.units_system,
            destinationIsResidential: true,
        };
    },
    mounted() {
        this.validateAddresses();
        if (this.items.length) {
            this.selectedLocationId = this.items[0].location || 0;
            this.notes = this.items[0].notes;
        }
        if (_.has(this.thePackage.info, 'selected_rate.carrier')) {
            this.rates = this.thePackage.info.shipment.rates;
            this.selectedRate = this.thePackage.info.selected_rate.id;
            this.shipment = this.thePackage.info.shipment;
        }
        this.shippingPrice = this.thePackage.shipping_cost || this.shippingPrice;
        this.insurancePrice = this.thePackage.insurance_value || this.insurancePrice;
    },
    watch: {
        shippingPrice(newPrice) {
            this.$emit('shipping-price-change', newPrice);
        },
        insurancePrice() {
            this.$emit('insurance-update', this.insuranceCost);
        },
        save() {
            if (this.save) {
                this.savePackage();
            }
        },
        customer() {
            this.validateAddresses();
        },
    },
    computed: {
        wrappingCost() {
            var total = 0;
            this.items.forEach(item => {
                item.box_inventories.forEach(material => {
                    total += material.retail_price;
                });
            });
            return total;
        },

        insuranceCost() {
            var value = parseFloat(this.insurancePrice);
            if (isNaN(value) || value === 0) {
                return 0;
            }
            if (value <= 100) {
                return 1;
            }
            return Math.round(value) / 100;
        },

        totalCost() {
            return parseFloat(this.wrappingCost) +
                parseFloat(this.thePackage.box.retail_price) +
                parseFloat(this.packingTimeCost) +
                parseFloat(this.insuranceCost) +
                parseFloat(this.shippingPrice) +
                parseFloat(this.adjustment);
        },

        selectedRateJson() {
            var json = '{}';
            this.rates.forEach(rate => {
                if (rate.id === this.selectedRate) {
                    json = JSON.stringify(rate);
                }
            });
            return json;
        },
    },
    methods: {
        /**
         * Generate name for input fields like: 'package[33][wrapping_cost]'.
         */
        name(field) {
            return 'packages[' + this.thePackage.id + '][' + field + ']';
        },

        /**
         * Load carrier rates.
         */
        getRates() {
            if (this.hasDimensionErrors) {
                return;
            }
            this.loadingRates = true;
            axios.get('/shipping/rates', { params: {
                item_id: this.items[0].id,
                width: this.dimensions.imperial.width,
                length: this.dimensions.imperial.length,
                height: this.dimensions.imperial.height,
                weight: this.dimensions.imperial.weight,
                residential: this.destinationIsResidential,
            }}).then(response => {
                this.shipment = response.data;
                this.selectedRate = null;
                this.rates = this.shipment.rates.sort(function(a, b) {
                    return parseFloat(a.charged_rate) < parseFloat(b.charged_rate) ? -1 : 1;
                });
                this.loadingRates = false;
                this.hasRates = true;
            });
        },

        /**
         * Buy shipment.
         */
        buyShipment() {
            var data = { rate_id: this.selectedRate, insurance: this.insurancePrice };
            this.loadingPostageLabel = true;

            axios.post('shipping/shipments/' + this.shipment.id + '/buy', data)
                .then(response => {
                    this.shipment = response.data;
                    this.shipmentPurchased = true;
                    this.loadingPostageLabel = false;
                    this.purchaseError = null;
                })
                .catch(error => {
                    this.purchaseError = error.response.data;
                    this.shipmentPurchased = false;
                    this.loadingPostageLabel = false;
                });
        },

        /**
         * Validate from/to addresses.
         */
        validateAddresses() {
            // validate from address
            axios.post('/shipping/addresses/validate', {
                address: this.appSettings.business_address,
                address2: this.appSettings.address2,
                city: this.appSettings.city,
                state: this.appSettings.state,
                zip: this.appSettings.zip,
            }).then(response => {
                var address = response.data;
                var verification = address.verifications.delivery;
                this.fromAddressStatus = verification.success ? 'valid' : 'invalid';
                this.fromAddressErrors = verification.errors;
            });

            // validate to address
            if (this.customer) {
                axios.post('/shipping/addresses/validate', {
                    address: this.customer.street,
                    address2: this.customer.address2,
                    city: this.customer.city,
                    state: this.customer.state,
                    zip:  this.customer.zip,
                }).then(response => {
                    var address = response.data;
                    var verification = address.verifications.delivery;
                    this.toAddressStatus = verification.success ? 'valid' : 'invalid';
                    this.toAddressErrors = verification.errors;
                    this.destinationIsResidential = address.residential;
                    this.correctToAddress(address);
                });
            }
        },

        /**
         * Compare destination address with corrected version.
         */
        correctToAddress(corrected) {
            this.toAddressCorrections = {};
            var remoteField, localField, originalValue, correctedValue;
            var addressFields = {
                street1: 'street',
                city: 'city',
                state: 'state',
                zip: 'zip',
            };
            for (remoteField in addressFields) {
                localField = addressFields[remoteField];
                originalValue = this.customer[localField].toUpperCase();
                correctedValue = corrected[remoteField].toUpperCase();
                if (originalValue !== correctedValue) {
                    this.toAddressCorrections[localField] = corrected[remoteField];
                }
            }
        },

        /**
         * Save Package.
         */
        savePackage() {
            var packages = {};
            var packageInfo = this.packageFields();

            packages[packageInfo.id] = packageInfo;
            this.$emit('saving', packageInfo);

            axios.put('/shipping/packages', { packages: packages })
                .then(() => this.$emit('saved', packageInfo));
        },

        /**
         * Prepare package info for POSTing to server.
         */
        packageFields() {
            var packageInfo = this.thePackage;
            packageInfo.total_cost = this.totalCost;
            packageInfo.adjustment = this.adjustment;
            packageInfo.length = this.dimensions.length;
            packageInfo.width = this.dimensions.width;
            packageInfo.height = this.dimensions.height;
            packageInfo.weight = this.dimensions.weight;
            packageInfo.length_system = this.lengthSystem;
            packageInfo.weight_system = this.weightSystem;
            packageInfo.shipment = JSON.stringify(this.shipment);
            packageInfo.selected_rate = this.selectedRateJson;
            packageInfo.insurance_value = this.insurancePrice;
            packageInfo.insurance_cost = this.insuranceCost;
            packageInfo.location = this.selectedLocationId;
            packageInfo.notes = this.notes;

            return packageInfo;
        },

        /**
         * Returns classes for given 'to address' field.
         */
        toClass(field) {
            return this.toAddressCorrections[field] ? 'text-danger pointer' : '';
        },

        /**
         * Save to address correction.
         */
        saveCorrection(field) {
            var address = {};
            address['customer_id'] = this.customer.id;
            address[field] = this.toAddressCorrections[field];
            Vue.set(this.toAddressStatuses, field, 'saving');
            this.$refs[field].focus(); // prevent popover from closing

            axios.put('/shipping/addresses', address).then(response => {
                this.toAddressStatuses[field] = 'saved';
                Vue.delete(this.toAddressCorrections, field);
                this.$emit('update-customer', response.data);
            });
        },
    },
}
</script>
