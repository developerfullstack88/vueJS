<template>
<div>
<select name="carrier_type"
    class="form-control"
    v-model="selectedCarrierType"
    :disabled="loading"
>
    <option :value="null">Select</option>
    <template v-for="carrierType in carrierTypes">
        <option v-if="carrierType.fields.credentials" :value="carrierType.type">
            <span v-if="carrierAccounts[carrierType.type]">&#10004;</span>
            {{ carrierType.readable }}
        </option>
    </template>
</select><br>

<div v-if="selectedCarrierType" class="row">
    <div class="col-sm-6">
        <div v-for="carrierType in carrierTypes" v-if="selectedCarrierType===carrierType.type">
            <div v-if="carrierType.readable==='USPS'" class="form-group">
                <input type="checkbox" v-model="use_default" id="use_default">
                <label for="use_default">Use Shipping Saint's {{ carrierType.readable }}</label>
            </div>
            <div v-for="field in carrierType.fields.credentials" class="form-group">
                <label :for="field.name">{{ field.label }}</label>
                <input :type="field.visibility === 'password' ? 'password' : 'text'"
                    :id="field.name"
                    class="form-control"
                    v-model="credentials[field.name]"
                    :disabled="use_default"
                >
            </div>
        </div>
    </div>

    <div class="col-sm-6">
        <div class="form-group">
            <label>Wholesale Rate Markup</label>
            <div class="row no-gutters">
                <div class="col-xs-5">
                    <div class="input-group">
                        <input type="number" v-model="adjustments['markup_percent']" class="form-control">
                        <div class="input-group-addon">%</div>
                    </div>
                </div>
                <h4 class="col-xs-2 text-center">+</h4>
                <div class="col-xs-5">
                    <div class="input-group">
                        <div class="input-group-addon">$</div>
                        <input type="number" v-model="adjustments['markup_amount']" class="form-control">
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>Min Markup Over Retail Rate</label>
            <div class="input-group">
                <div class="input-group-addon">$</div>
                <input type="number" v-model="adjustments['markup_min']" class="form-control">
            </div>
        </div>
        <div class="form-group">
            <label>Max Markup Over Retail Rate</label>
            <div class="input-group">
                <div class="input-group-addon">$</div>
                <input type="number" class="form-control" v-model="adjustments['markup_max']">
            </div>
        </div>
    </div>
</div>


<div v-if="selectedCarrierType && ! carrierAccounts[selectedCarrierType]">
    <button v-if=" ! loading" @click="connect" type="button" class="btn btn-primary">
        Connect Carrier Account
    </button>

    <button v-if="loading" disabled type="button" class="btn btn-primary">
        Connecting <span class="glyphicon glyphicon-repeat spinning loading"></span>
    </button>
</div>

<div v-if="selectedCarrierType && carrierAccounts[selectedCarrierType]">
    <button @click="deleteCarrier" :disabled="loading" type="button" class="btn btn-danger">
        Delete
    </button>
    <button @click="updateCarrier" :disabled="loading" type="button" class="btn btn-primary">
        Update
    </button>
</div>

<transition name="fade">
    <div v-if="connectedSuccessfully" class="alert alert-success">
        Connected <span class="glyphicon glyphicon-ok"></span>
    </div>
    <div v-if="updatedSuccessfully" class="alert alert-success">
        Updated <span class="glyphicon glyphicon-ok"></span>
    </div>
    <div v-if="deletedSuccessfully" class="alert alert-success">
        Deleted <span class="glyphicon glyphicon-ok"></span>
    </div>
</transition>

</div>
</template>

<script>
export default {
    props: ['carrierTypes', 'carrierAccounts'],
    data() {
        return {
            selectedCarrierType: null,
            credentials: {},
            adjustments: {},
            loading: false,
            connectedSuccessfully: false,
            updatedSuccessfully: false,
            deletedSuccessfully: false,
            use_default: false,
        };
    },
    watch: {
        selectedCarrierType() {
            if (this.carrierAccounts[this.selectedCarrierType]) {
                var account = this.carrierAccounts[this.selectedCarrierType];
                this.credentials = account.info.credentials || {};
                this.adjustments = account.adjustments;

                // prevent credentials or adjustments from being set to empty arrays
                this.credentials = Array.isArray(this.credentials) ? {} : this.credentials;
                this.adjustments = Array.isArray(this.adjustments) ? {} : this.adjustments;
                this.use_default = account.info.use_default || false;
            } else {
                this.credentials = {};
                this.adjustments = {};
                this.use_default = this.selectedCarrierType === 'UspsAccount';
            }
        },
    },
    methods: {
        /**
         * Create new connection to a carrier account.
         */
        connect() {
            this.loading = true;
            axios.post('/shipping/carriers', {
                carrier_info: {
                    type: this.selectedCarrierType,
                    credentials: this.credentials,
                    adjustments: this.adjustments,
                    use_default: this.use_default,
                },
            }).then(response => {
                Vue.set(this.carrierAccounts, this.selectedCarrierType, response.data);
                this.loading = false;
                this.connectedSuccessfully = true;
                setTimeout((() => this.connectedSuccessfully = false), 2000);
            });
        },

        /**
         * Update carrier account connection info.
         */
        updateCarrier() {
            var carrier = this.carrierAccounts[this.selectedCarrierType];
            this.loading = true;
            axios.put('/shipping/carriers/' + carrier.id, { carrier_info: {
                credentials: this.credentials,
                adjustments: this.adjustments,
                use_default: this.use_default,
            }}).then(response => {
                Vue.set(this.carrierAccounts, this.selectedCarrierType, response.data);
                this.loading = false;
                this.updatedSuccessfully = true;
                setTimeout((() => this.updatedSuccessfully = false), 2000);
            });
        },

        /**
         * Delete a carrier account connection.
         */
        deleteCarrier() {
            var carrier = this.carrierAccounts[this.selectedCarrierType];
            this.loading = true;
            axios.delete('/shipping/carriers/' + carrier.id).then(response => {
                Vue.delete(this.carrierAccounts, this.selectedCarrierType);
                this.credentials = [];
                this.loading = false;
                this.deletedSuccessfully = true;
                setTimeout((() => this.deletedSuccessfully = false), 2000);
            });
        },
    },
}
</script>
