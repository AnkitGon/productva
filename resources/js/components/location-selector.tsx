import { useMemo } from 'react';
import { Country, State } from 'country-state-city';
import { SearchableSelect } from '@/components/ui/searchable-select';

interface CountrySelectProps {
    value?: string;
    onChange: (countryName: string, countryCode: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function CountrySelect({
    value,
    onChange,
    placeholder = 'Select country...',
    disabled = false,
    className,
}: CountrySelectProps) {
    const countries = useMemo(() => {
        return Country.getAllCountries().map((c) => ({
            value: c.isoCode,
            label: c.name,
        }));
    }, []);

    const selectedIsoCode = useMemo(() => {
        if (!value) return '';
        const found = Country.getAllCountries().find(
            (c) => c.isoCode === value || c.name === value
        );
        return found ? found.isoCode : '';
    }, [value]);

    const handleChange = (isoCode: string) => {
        const found = Country.getCountryByCode(isoCode);
        if (found) {
            onChange(found.name, found.isoCode);
        } else {
            onChange('', '');
        }
    };

    return (
        <SearchableSelect
            options={countries}
            value={selectedIsoCode}
            onChange={handleChange}
            placeholder={placeholder}
            disabled={disabled}
            className={className}
        />
    );
}

interface StateSelectProps {
    countryCode?: string;
    value?: string;
    onChange: (stateName: string, stateCode: string) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
}

export function StateSelect({
    countryCode,
    value,
    onChange,
    placeholder = 'Select state...',
    disabled = false,
    className,
}: StateSelectProps) {
    const states = useMemo(() => {
        if (!countryCode) return [];
        return State.getStatesOfCountry(countryCode).map((s) => ({
            value: s.isoCode,
            label: s.name,
        }));
    }, [countryCode]);

    const selectedIsoCode = useMemo(() => {
        if (!value || !countryCode) return '';
        const found = State.getStatesOfCountry(countryCode).find(
            (s) => s.isoCode === value || s.name === value
        );
        return found ? found.isoCode : '';
    }, [value, countryCode]);

    const handleChange = (isoCode: string) => {
        if (!countryCode) return;
        const found = State.getStateByCodeAndCountry(isoCode, countryCode);
        if (found) {
            onChange(found.name, found.isoCode);
        } else {
            onChange('', '');
        }
    };

    return (
        <SearchableSelect
            options={states}
            value={selectedIsoCode}
            onChange={handleChange}
            placeholder={countryCode ? placeholder : 'Select Country first'}
            disabled={disabled || !countryCode}
            className={className}
        />
    );
}
