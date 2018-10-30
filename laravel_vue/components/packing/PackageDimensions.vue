<template>
<div>
    <div class="row" v-if="editable">
        <div id="dimensions-group" class="form-group bs-reset col-sm-6">
            <div class="row no-gutters">
                <div :class="'col-sm-3 ' + (errors['length'].length ? 'has-error' : '')">
                    <label class="control-label no-bold" for="length">Length:</label>
                    <input type="number"
                        :name="name('length')"
                        v-model.number="length"
                        id="length"
                        step="1"
                        class="form-control"
                        placeholder="Length"
                        @change="validate()"
                        @keyup="validate()"
                    >
                    <span class="help-block" v-text="errors['length']"/>
                </div>
                <div :class="'col-sm-3 ' + (errors['width'].length ? 'has-error' : '')">
                    <label class="control-label no-bold" for="width">Width:</label>
                    <input type="number"
                        :name="name('width')"
                        v-model.number="width"
                        id="width"
                        step="1"
                        class="form-control"
                        placeholder="Width"
                        @change="validate()"
                        @keyup="validate()"
                    >
                    <span class="help-block" v-text="errors['width']"/>
                </div>
                <div :class="'col-sm-6 ' + (errors['height'].length ? 'has-error' : '')">
                    <label class="control-label no-bold" for="height">Height:</label>
                    <div class="input-group">
                        <input type="number"
                            :name="name('height')"
                            v-model.number="height"
                            id="height"
                            step="1"
                            class="form-control"
                            placeholder="Height"
                            @change="validate()"
                            @keyup="validate()"
                        >
                        <span class="input-group-addon with-control">
                            <select :name="name('length_system')" v-model="lengthSystem">
                                <option value="metric">mm</option>
                                <option value="imperial">in</option>
                            </select>
                        </span>
                    </div>
                    <span class="help-block" v-text="errors['height']"/>
                </div>
            </div>
        </div>

        <div id="weight-group" :class="'col-sm-6 form-group ' + (errors['weight'].length ? 'has-error' : '')">
            <label class="control-label no-bold">Weight:</label>
            <input type="hidden" :name="name('weight')" v-model="weight">
            <div class="row" v-if="weightSystem === 'imperial'">
                <div class="col-xs-6">
                    <div class="input-group">
                        <input type="number"
                            v-model.number="weightLb"
                            class="form-control"
                            placeholder="Pounds"
                            @change="setWeightFromLb();"
                            @keyup="setWeightFromLb();"
                            @blur="weightLb = weightLb || 0; setWeightFromLb()"
                        >
                        <span class="input-group-addon with-control">
                            <select :name="name('weight_system')" v-model="weightSystem">
                                <option value="imperial">lb</option>
                                <option value="metric">kg</option>
                            </select>
                        </span>
                    </div>
                    <span class="help-block" v-text="errors['weight']"/>
                </div>
                <div class="col-xs-6">
                    <div class="input-group">
                        <input type="number"
                            v-model.number="weightOz"
                            class="form-control"
                            placeholder="Ounces"
                            @change="setWeightFromOz();"
                            @keyup="setWeightFromOz();"
                            @blur="weightOz = weightOz || 0; setWeightFromOz()"
                        >
                        <span class="input-group-addon">oz</span>
                    </div>
                </div>
            </div>
            <div class="row" v-if="weightSystem === 'metric'">
                <div class="col-xs-12">
                    <div class="input-group">
                        <input type="number"
                            step="0.01"
                            v-model.number="weightKg"
                            class="form-control"
                            placeholder="Kilograms"
                            @change="setWeightFromMetric();"
                            @keyup="setWeightFromMetric();"
                            @blur="weightKg = weightKg || 0; setWeightFromMetric()"
                        >
                        <span class="input-group-addon with-control">
                            <select :name="name('weight_system')" v-model="weightSystem">
                                <option value="imperial">lb</option>
                                <option value="metric">kg</option>
                            </select>
                        </span>
                    </div>
                    <span class="help-block" v-text="errors['weight']"/>
                </div>
            </div>
        </div>
    </div>

    <div class="row" v-if=" ! editable">
        <div id="dimensions-group" class="form-group bs-reset col-sm-6">
            <div class="row">
                <div class="col-sm-4">
                    <label class="control-label no-bold" for="length">Length:</label>
                    {{ length }}{{ lengthSystem === 'metric' ? 'mm' : 'in' }}
                </div>
                <div class="col-sm-4">
                    <label class="control-label no-bold" for="width">Width:</label>
                    {{ width }}{{ lengthSystem === 'metric' ? 'mm' : 'in' }}
                </div>
                <div class="col-sm-4">
                    <label class="control-label no-bold" for="height">Height:</label>
                    {{ height }}{{ lengthSystem === 'metric' ? 'mm' : 'in' }}
                </div>
            </div>
        </div>

        <div id="weight-group" class="col-sm-6 form-group">
            <label class="control-label no-bold">Weight:</label>
            <div class="row" v-if="weightSystem === 'imperial'">
                <div class="col-xs-6">
                    <div class="input-group">
                        {{ weightLb }}lb
                    </div>
                </div>
                <div class="col-xs-6">
                    <div class="input-group">
                        {{ weightOz }}oz
                    </div>
                </div>
            </div>
            <div class="row" v-if="weightSystem === 'metric'">
                <div class="col-xs-12">
                    {{ weightKg }}kg
                </div>
            </div>
        </div>
    </div>
</div>
</template>

<script>
export default {
    props: {
        thePackage: Object,
        appSettings: Object,
        editable: {
            type: Boolean,
            default: true,
        },
    },
    data() {
        return {
            length: parseFloat((this.thePackage.length || 0).toFixed(2)),
            width: parseFloat((this.thePackage.width || 0).toFixed(2)),
            height: parseFloat((this.thePackage.height || 0).toFixed(2)),
            weight: (this.thePackage.weight || 0),
            weightKg: 0,
            weightLb: 0,
            weightOz: 0,
            lengthSystem: this.appSettings.units_system,
            weightSystem: this.appSettings.units_system,
            errors: {length: '', width: '', height: '', weight: ''},
        };
    },

    mounted() {
        this.updateWeightFields();
        this.updateDimensions();
    },

    watch: {
        lengthSystem(newSystem, oldSystem) {
            var ratio = 1;
            var precision = newSystem === 'imperial' ? 2 : 0;

            if (oldSystem === 'imperial' && newSystem === 'metric') {
                ratio = 25.4;
            }
            if (oldSystem === 'metric' && newSystem === 'imperial') {
                ratio = 0.0393701;
            }
            this.length = (this.length * ratio).toFixed(precision);
            this.width = (this.width * ratio).toFixed(precision);
            this.height = (this.height * ratio).toFixed(precision);
            this.$emit('length-system-update', newSystem);
        },
        weightSystem(newSystem, oldSystem) {
            if (oldSystem === 'imperial' && newSystem === 'metric') {
                this.weight = parseInt((this.weight * 28.3495).toFixed(0));
                this.updateWeightFields();
            }
            if (oldSystem === 'metric' && newSystem === 'imperial') {
                this.weight = parseInt((this.weight * 0.035274).toFixed(0));
                this.updateWeightFields();
            }
            this.$emit('weight-system-update', newSystem);
        },
        length() {
            this.updateDimensions();
        },
        width() {
            this.updateDimensions();
        },
        height() {
            this.updateDimensions();
        },
        weight() {
            this.updateDimensions();
        },
        errors() {
            this.updateErrors();
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
         * Validate fields.
         */
        validate() {
            var hasErrors = false;
            this.errors = {length: '', width: '', height: '', weight: ''};
            ['length', 'width', 'height'].forEach(field => {
                if ( ! this[field]) {
                    Vue.set(this.errors, field, field + ' required.');
                    hasErrors = true;
                }
            });
            if ( ! this.weight) {
                Vue.set(this.errors, 'weight', 'weight required.');
                hasErrors = true;
            }
            return hasErrors;
        },


        /**
         * Get weight in kilograms.
         */
        weightInKg() {
            switch (this.weightSystem || this.appSettings.units_system) {
                case 'metric':
                    return parseFloat(((this.weight || 0) / 1000).toFixed(3));
                case 'imperial':
                    return parseFloat(((this.weight || 0) * 28.3495 / 1000).toFixed(3));
            }
        },

        /**
         * Get weight in imperial measurements.
         */
        weightInImperial() {
            switch (this.weightSystem || this.appSettings.units_system) {
                case 'metric':
                    var ounces = (this.weight || 0) * 0.035274;
                    return {
                        pounds: Math.floor(ounces / 16),
                        ounces: ounces % 16,
                    };
                case 'imperial':
                    return {
                        pounds: Math.floor(this.weight / 16),
                        ounces: this.weight % 16,
                    }
            }
        },

        /**
         * Get total weight in ounces.
         */
        weightInOunces() {
            var ounces = (this.weightLb || 0) * 16 + (this.weightOz || 0);
            return parseInt(ounces.toFixed(0));
        },

        /**
         * Get width in inches.
         */
        widthInInches() {
            return this.lengthSystem === 'imperial' ? this.width : this.width * 0.0393701;
        },
        /**
         * Get length in inches.
         */
        lengthInInches() {
            return this.lengthSystem === 'imperial' ? this.length : this.length * 0.0393701;
        },
        /**
         * Get height in inches.
         */
        heightInInches() {
            return this.lengthSystem === 'imperial' ? this.height : this.height * 0.0393701;
        },

        /**
         * Get width in millimeters.
         */
        widthInMillimeters() {
            return this.lengthSystem === 'metric' ? this.width : this.width * 25.4;
        },
        /**
         * Get length in millimeters.
         */
        lengthInMillimeters() {
            return this.lengthSystem === 'metric' ? this.length : this.length * 25.4;
        },
        /**
         * Get height in millimeters.
         */
        heightInMillimeters() {
            return this.lengthSystem === 'metric' ? this.height : this.height * 25.4;
        },

        /**
         * Update displayed weight fields.
         */
        updateWeightFields() {
            this.weightKg = this.weightInKg();
            this.weightLb = this.weightInImperial().pounds;
            this.weightOz = this.weightInImperial().ounces;
        },

        setWeightFromMetric() {
            if (this.weightKg !== '') {
                var grams = (this.weightKg || 0) * 1000;
                this.weight = parseInt(grams.toFixed(0));
                this.updateWeightFields();
                this.validate();
            }
        },
        setWeightFromLb() {
            if (this.weightLb !== '') {
                this.setWeightFromImperial();
            }
        },
        setWeightFromOz() {
            if (this.weightOz !== '') {
                this.setWeightFromImperial();
            }
        },
        setWeightFromImperial() {
            this.weight = this.weightInOunces();
            this.updateWeightFields();
            this.validate();
        },

        /**
         * Emit package dimensions.
         */
        updateDimensions() {
            this.$emit('dimensions-update', {
                imperial: {
                    width: this.widthInInches(),
                    length: this.lengthInInches(),
                    height: this.heightInInches(),
                    weight: this.weightInOunces(),
                },
                metric: {
                    width: this.widthInMillimeters(),
                    length: this.lengthInMillimeters(),
                    height: this.heightInMillimeters(),
                    weight: this.weightInKg * 1000,
                },
                width: this.width,
                length: this.length,
                height: this.height,
                weight: this.weight,
            });
            this.validate();
        },
        /**
         * Emit errors.
         */
        updateErrors() {
            var errorsArray = [
                this.errors.width,
                this.errors.length,
                this.errors.height,
                this.errors.weight,
            ];
            errorsArray = errorsArray.filter(error => error != '');
            this.$emit('errors-update', errorsArray);
        }
    },
}
</script>
