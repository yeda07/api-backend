<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->currencies() as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }

    private function currencies(): array
    {
        return collect([
            ['AED', 'UAE Dirham', 'د.إ'], ['AFN', 'Afghan Afghani', '؋'], ['ALL', 'Albanian Lek', 'L'], ['AMD', 'Armenian Dram', '֏'],
            ['ANG', 'Netherlands Antillean Guilder', 'ƒ'], ['AOA', 'Angolan Kwanza', 'Kz'], ['ARS', 'Peso argentino', '$'], ['AUD', 'Australian Dollar', 'A$'],
            ['AWG', 'Aruban Florin', 'ƒ'], ['AZN', 'Azerbaijani Manat', '₼'], ['BAM', 'Bosnia-Herzegovina Convertible Mark', 'KM'], ['BBD', 'Barbadian Dollar', 'Bds$'],
            ['BDT', 'Bangladeshi Taka', '৳'], ['BGN', 'Bulgarian Lev', 'лв'], ['BHD', 'Bahraini Dinar', 'BD', 3], ['BIF', 'Burundian Franc', 'FBu', 0],
            ['BMD', 'Bermudian Dollar', 'BD$'], ['BND', 'Brunei Dollar', 'B$'], ['BOB', 'Bolivian Boliviano', 'Bs'], ['BOV', 'Bolivian Mvdol', 'BOV'],
            ['BRL', 'Brazilian Real', 'R$'], ['BSD', 'Bahamian Dollar', 'B$'], ['BTN', 'Bhutanese Ngultrum', 'Nu.'], ['BWP', 'Botswana Pula', 'P'],
            ['BYN', 'Belarusian Ruble', 'Br'], ['BZD', 'Belize Dollar', 'BZ$'], ['CAD', 'Canadian Dollar', 'C$'], ['CDF', 'Congolese Franc', 'FC'],
            ['CHE', 'WIR Euro', 'CHE'], ['CHF', 'Swiss Franc', 'CHF'], ['CHW', 'WIR Franc', 'CHW'], ['CLF', 'Unidad de Fomento', 'UF', 4],
            ['CLP', 'Peso chileno', '$', 0], ['CNY', 'Chinese Yuan', '¥'], ['COP', 'Peso colombiano', '$'], ['COU', 'Unidad de Valor Real', 'COU'],
            ['CRC', 'Costa Rican Colon', '₡'], ['CUC', 'Cuban Convertible Peso', 'CUC$'], ['CUP', 'Cuban Peso', '$'], ['CVE', 'Cape Verdean Escudo', '$'],
            ['CZK', 'Czech Koruna', 'Kč'], ['DJF', 'Djiboutian Franc', 'Fdj', 0], ['DKK', 'Danish Krone', 'kr'], ['DOP', 'Dominican Peso', 'RD$'],
            ['DZD', 'Algerian Dinar', 'دج'], ['EGP', 'Egyptian Pound', 'E£'], ['ERN', 'Eritrean Nakfa', 'Nfk'], ['ETB', 'Ethiopian Birr', 'Br'],
            ['EUR', 'Euro', '€'], ['FJD', 'Fijian Dollar', 'FJ$'], ['FKP', 'Falkland Islands Pound', '£'], ['GBP', 'Pound Sterling', '£'],
            ['GEL', 'Georgian Lari', '₾'], ['GHS', 'Ghanaian Cedi', '₵'], ['GIP', 'Gibraltar Pound', '£'], ['GMD', 'Gambian Dalasi', 'D'],
            ['GNF', 'Guinean Franc', 'FG', 0], ['GTQ', 'Guatemalan Quetzal', 'Q'], ['GYD', 'Guyanese Dollar', 'G$'], ['HKD', 'Hong Kong Dollar', 'HK$'],
            ['HNL', 'Honduran Lempira', 'L'], ['HTG', 'Haitian Gourde', 'G'], ['HUF', 'Hungarian Forint', 'Ft'], ['IDR', 'Indonesian Rupiah', 'Rp'],
            ['ILS', 'Israeli New Shekel', '₪'], ['INR', 'Indian Rupee', '₹'], ['IQD', 'Iraqi Dinar', 'ع.د', 3], ['IRR', 'Iranian Rial', '﷼'],
            ['ISK', 'Icelandic Krona', 'kr', 0], ['JMD', 'Jamaican Dollar', 'J$'], ['JOD', 'Jordanian Dinar', 'JD', 3], ['JPY', 'Japanese Yen', '¥', 0],
            ['KES', 'Kenyan Shilling', 'KSh'], ['KGS', 'Kyrgyzstani Som', 'с'], ['KHR', 'Cambodian Riel', '៛'], ['KMF', 'Comorian Franc', 'CF', 0],
            ['KPW', 'North Korean Won', '₩'], ['KRW', 'South Korean Won', '₩', 0], ['KWD', 'Kuwaiti Dinar', 'KD', 3], ['KYD', 'Cayman Islands Dollar', 'CI$'],
            ['KZT', 'Kazakhstani Tenge', '₸'], ['LAK', 'Lao Kip', '₭'], ['LBP', 'Lebanese Pound', 'ل.ل'], ['LKR', 'Sri Lankan Rupee', 'Rs'],
            ['LRD', 'Liberian Dollar', 'L$'], ['LSL', 'Lesotho Loti', 'L'], ['LYD', 'Libyan Dinar', 'LD', 3], ['MAD', 'Moroccan Dirham', 'DH'],
            ['MDL', 'Moldovan Leu', 'L'], ['MGA', 'Malagasy Ariary', 'Ar'], ['MKD', 'Macedonian Denar', 'ден'], ['MMK', 'Myanmar Kyat', 'K'],
            ['MNT', 'Mongolian Tugrik', '₮'], ['MOP', 'Macanese Pataca', 'MOP$'], ['MRU', 'Mauritanian Ouguiya', 'UM'], ['MUR', 'Mauritian Rupee', '₨'],
            ['MVR', 'Maldivian Rufiyaa', 'Rf'], ['MWK', 'Malawian Kwacha', 'MK'], ['MXN', 'Peso mexicano', '$'], ['MXV', 'Mexican Unidad de Inversion', 'MXV'],
            ['MYR', 'Malaysian Ringgit', 'RM'], ['MZN', 'Mozambican Metical', 'MT'], ['NAD', 'Namibian Dollar', 'N$'], ['NGN', 'Nigerian Naira', '₦'],
            ['NIO', 'Nicaraguan Cordoba', 'C$'], ['NOK', 'Norwegian Krone', 'kr'], ['NPR', 'Nepalese Rupee', '₨'], ['NZD', 'New Zealand Dollar', 'NZ$'],
            ['OMR', 'Omani Rial', 'OMR', 3], ['PAB', 'Panamanian Balboa', 'B/.'], ['PEN', 'Sol peruano', 'S/'], ['PGK', 'Papua New Guinean Kina', 'K'],
            ['PHP', 'Philippine Peso', '₱'], ['PKR', 'Pakistani Rupee', '₨'], ['PLN', 'Polish Zloty', 'zł'], ['PYG', 'Paraguayan Guarani', '₲', 0],
            ['QAR', 'Qatari Riyal', 'QR'], ['RON', 'Romanian Leu', 'lei'], ['RSD', 'Serbian Dinar', 'дин'], ['RUB', 'Russian Ruble', '₽'],
            ['RWF', 'Rwandan Franc', 'RF', 0], ['SAR', 'Saudi Riyal', '﷼'], ['SBD', 'Solomon Islands Dollar', 'SI$'], ['SCR', 'Seychellois Rupee', '₨'],
            ['SDG', 'Sudanese Pound', 'SDG'], ['SEK', 'Swedish Krona', 'kr'], ['SGD', 'Singapore Dollar', 'S$'], ['SHP', 'Saint Helena Pound', '£'],
            ['SLE', 'Sierra Leonean Leone', 'Le'], ['SLL', 'Sierra Leonean Leone (old)', 'Le'], ['SOS', 'Somali Shilling', 'Sh'], ['SRD', 'Surinamese Dollar', 'SRD$'],
            ['SSP', 'South Sudanese Pound', 'SSP'], ['STN', 'Sao Tome and Principe Dobra', 'Db'], ['SVC', 'Salvadoran Colon', '₡'], ['SYP', 'Syrian Pound', '£S'],
            ['SZL', 'Swazi Lilangeni', 'E'], ['THB', 'Thai Baht', '฿'], ['TJS', 'Tajikistani Somoni', 'SM'], ['TMT', 'Turkmenistani Manat', 'm'],
            ['TND', 'Tunisian Dinar', 'DT', 3], ['TOP', 'Tongan Paanga', 'T$'], ['TRY', 'Turkish Lira', '₺'], ['TTD', 'Trinidad and Tobago Dollar', 'TT$'],
            ['TWD', 'New Taiwan Dollar', 'NT$'], ['TZS', 'Tanzanian Shilling', 'TSh'], ['UAH', 'Ukrainian Hryvnia', '₴'], ['UGX', 'Ugandan Shilling', 'USh', 0],
            ['USD', 'US Dollar', 'US$'], ['USN', 'US Dollar Next Day', 'USN'], ['UYI', 'Uruguay Peso en Unidades Indexadas', 'UYI', 0], ['UYU', 'Uruguayan Peso', '$U'],
            ['UYW', 'Unidad Previsional', 'UYW', 4], ['UZS', 'Uzbekistani Som', 'soʻm'], ['VED', 'Venezuelan Digital Bolivar', 'Bs.D'], ['VES', 'Venezuelan Bolivar', 'Bs.'],
            ['VND', 'Vietnamese Dong', '₫', 0], ['VUV', 'Vanuatu Vatu', 'VT', 0], ['WST', 'Samoan Tala', 'WS$'], ['XAF', 'Central African CFA Franc', 'FCFA', 0],
            ['XCD', 'East Caribbean Dollar', 'EC$'], ['XDR', 'Special Drawing Rights', 'XDR'], ['XOF', 'West African CFA Franc', 'CFA', 0], ['XPF', 'CFP Franc', '₣', 0],
            ['YER', 'Yemeni Rial', '﷼'], ['ZAR', 'South African Rand', 'R'], ['ZMW', 'Zambian Kwacha', 'ZK'], ['ZWL', 'Zimbabwean Dollar', 'Z$'],
        ])->map(fn (array $currency) => [
            'code' => $currency[0],
            'name' => $currency[1],
            'symbol' => $currency[2],
            'decimal_places' => $currency[3] ?? 2,
            'thousands_separator' => $this->latinSeparator($currency[0]) ? '.' : ',',
            'decimal_separator' => $this->latinSeparator($currency[0]) ? ',' : '.',
        ])->all();
    }

    private function latinSeparator(string $code): bool
    {
        return in_array($code, ['ARS', 'BOB', 'BRL', 'CLP', 'COP', 'CRC', 'EUR', 'PYG', 'UYU', 'VES'], true);
    }
}
