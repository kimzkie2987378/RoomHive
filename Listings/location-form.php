<?php
/* =========================
   location-form.php
========================== */

/*
 * RoomHive — Property Location Form
 *
 * ORDER:
 * 1. Province
 * 2. City / Municipality
 * 3. Barangay
 * 4. Street / Full Address
 *
 * Province is fixed to Negros Oriental.
 * City/Municipality and Barangay use cascading dropdowns.
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/webprogg/config/negros-oriental-locations.php';

/*
 * Preserve submitted values if the form is submitted
 * and needs to be displayed again.
 */
$selectedCity = $_POST['city'] ?? '';
$selectedBarangay = $_POST['barangay'] ?? '';
$streetAddress = $_POST['street_address'] ?? '';

/*
 * Make sure the values are strings.
 */
$selectedCity = is_string($selectedCity) ? $selectedCity : '';
$selectedBarangay = is_string($selectedBarangay) ? $selectedBarangay : '';
$streetAddress = is_string($streetAddress) ? $streetAddress : '';
?>

<!-- =========================
     LOCATION FORM
========================== -->

<div class="location-form-group">

    <h3 class="location-form-heading">
        Property Location
    </h3>


    <!-- =========================
         1. PROVINCE
    ========================== -->

    <div class="location-field">

        <label for="province">
            Province
        </label>

        <div class="location-input-wrapper">

            <img
                src="/webprogg/images/GPSIcon.png"
                alt=""
                class="location-icon"
            >

            <input
                type="text"
                id="province"
                class="location-input"
                value="<?= htmlspecialchars(
                    $province,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                readonly
            >

            <!-- Send province to PHP -->
            <input
                type="hidden"
                name="province"
                value="<?= htmlspecialchars(
                    $province,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

        </div>

    </div>


    <!-- =========================
         2. CITY / MUNICIPALITY
    ========================== -->

    <div class="location-field">

        <label for="city-select">
            City / Municipality
        </label>

        <div class="location-input-wrapper">

            <img
                src="/webprogg/images/HouseIcon.png"
                alt=""
                class="location-icon"
            >

            <select
                name="city"
                id="city-select"
                class="location-input"
                required
            >

                <option value="">
                    Select city / municipality
                </option>

                <?php foreach (
                    $negrosOrientalLocations
                    as $citySlug => $cityData
                ): ?>

                    <option
                        value="<?= htmlspecialchars(
                            $citySlug,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= $selectedCity === $citySlug
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars(
                            $cityData['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        <?= $cityData['type'] === 'city'
                            ? ' City'
                            : ' Municipality' ?>

                    </option>

                <?php endforeach; ?>

            </select>

            <img
                src="/webprogg/images/DownwardArrow.png"
                alt=""
                class="location-arrow"
            >

        </div>

    </div>


    <!-- =========================
         3. BARANGAY
    ========================== -->

    <div class="location-field">

        <label for="barangay-select">
            Barangay
        </label>

        <div class="location-input-wrapper">

            <img
                src="/webprogg/images/GPSIcon.png"
                alt=""
                class="location-icon"
            >

            <select
                name="barangay"
                id="barangay-select"
                class="location-input"
                required
                disabled
            >

                <option value="">
                    Select barangay
                </option>

            </select>

            <img
                src="/webprogg/images/DownwardArrow.png"
                alt=""
                class="location-arrow"
            >

        </div>

    </div>


    <!-- =========================
         4. STREET / FULL ADDRESS
    ========================== -->

    <div class="location-field">

        <label for="street-address">
            Street / Full Address
        </label>

        <div class="location-input-wrapper">

            <img
                src="/webprogg/images/GPSIcon.png"
                alt=""
                class="location-icon"
            >

            <input
                type="text"
                name="street_address"
                id="street-address"
                class="location-input"
                placeholder="e.g. Hibbard Avenue"
                value="<?= htmlspecialchars(
                    $streetAddress,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>

    </div>

</div>


<!-- =========================
     LOCATION FORM CSS
========================== -->

<style>

.location-form-group {
    width: 100%;
    background: #ffffff;
    border: 1px solid #e5e5e5;
    border-radius: 12px;
    padding: 22px;
    box-sizing: border-box;
}

.location-form-heading {
    margin: 0 0 20px;
    font-family: 'Poppins', sans-serif;
    font-size: 20px;
    font-weight: 600;
    color: #1C2A38;
}

.location-field {
    width: 100%;
    margin-bottom: 18px;
}

.location-field:last-child {
    margin-bottom: 0;
}

.location-field label {
    display: block;
    margin-bottom: 7px;
    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #1C2A38;
}

.location-input-wrapper {
    position: relative;
    width: 100%;
    display: flex;
    align-items: center;
}

.location-icon {
    position: absolute;
    left: 15px;
    width: 20px;
    height: 20px;
    object-fit: contain;
    z-index: 2;
}

.location-input {
    width: 100%;
    height: 48px;
    box-sizing: border-box;

    padding: 0 45px 0 48px;

    border: 1px solid #d9d9d9;
    border-radius: 8px;

    background: #ffffff;

    font-family: 'Poppins', sans-serif;
    font-size: 14px;
    color: #1C2A38;

    outline: none;
}

.location-input:focus {
    border-color: #eda423;
}

.location-input::placeholder {
    color: #999999;
}

.location-input[readonly] {
    background: #f5f5f5;
    cursor: default;
}

.location-input:disabled {
    background: #f5f5f5;
    color: #999999;
    cursor: not-allowed;
}

.location-arrow {
    position: absolute;
    right: 15px;
    width: 13px;
    height: 13px;
    object-fit: contain;
    pointer-events: none;
}

select.location-input {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    cursor: pointer;
}

select.location-input:disabled {
    cursor: not-allowed;
}

</style>


<!-- =========================
     CITY → BARANGAY JAVASCRIPT
========================== -->

<script>

const roomhiveLocations =
    <?= json_encode(
        $negrosOrientalLocations,
        JSON_UNESCAPED_UNICODE |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;


document.addEventListener(
    'DOMContentLoaded',
    function () {

        const citySelect =
            document.getElementById('city-select');

        const barangaySelect =
            document.getElementById('barangay-select');


        /*
         * Populate barangays based on
         * selected city / municipality.
         */
        function populateBarangays(
            citySlug,
            selectedBarangay = ''
        ) {

            /*
             * Reset barangay dropdown.
             */
            barangaySelect.innerHTML =
                '<option value="">Select barangay</option>';


            /*
             * No city selected.
             */
            if (
                !citySlug ||
                !roomhiveLocations[citySlug]
            ) {

                barangaySelect.disabled = true;

                return;
            }


            const barangays =
                roomhiveLocations[citySlug].barangays;


            /*
             * Add barangays.
             */
            Object.keys(barangays).forEach(
                function (barangaySlug) {

                    const option =
                        document.createElement('option');

                    option.value =
                        barangaySlug;

                    option.textContent =
                        barangays[barangaySlug];


                    /*
                     * Restore previously
                     * selected barangay.
                     */
                    if (
                        selectedBarangay &&
                        barangaySlug === selectedBarangay
                    ) {

                        option.selected = true;

                    }


                    barangaySelect.appendChild(option);

                }
            );


            /*
             * Enable barangay dropdown.
             */
            barangaySelect.disabled = false;

        }


        /*
         * When city changes,
         * update barangay list.
         */
        citySelect.addEventListener(
            'change',
            function () {

                populateBarangays(
                    this.value,
                    ''
                );

            }
        );


        /*
         * Restore values after
         * validation failure.
         */
        const initialCity =
            <?= json_encode(
                $selectedCity,
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) ?>;


        const initialBarangay =
            <?= json_encode(
                $selectedBarangay,
                JSON_HEX_TAG |
                JSON_HEX_AMP |
                JSON_HEX_APOS |
                JSON_HEX_QUOT
            ) ?>;


        if (initialCity) {

            populateBarangays(
                initialCity,
                initialBarangay
            );

        }

    }
);

</script>
