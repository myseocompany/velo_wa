import { useMemo, useState } from 'react';

interface Props {
    name: string | null;
    imageUrl?: string | null;
    sizeClass?: string;
}

export default function ContactAvatar({
    name,
    imageUrl,
    sizeClass = 'h-10 w-10',
}: Props) {
    const [hasImageError, setHasImageError] = useState(false);

    const initials = useMemo(() => {
        return (name ?? '?')
            .split(' ')
            .slice(0, 2)
            .map((word) => word[0] ?? '')
            .join('')
            .toUpperCase();
    }, [name]);

    const canShowImage = Boolean(imageUrl) && !hasImageError;

    if (canShowImage) {
        return (
            <img
                src={imageUrl ?? undefined}
                alt={name ?? 'Contacto'}
                className={`${sizeClass} flex-shrari-0 rounded-full object-cover`}
                onError={() => setHasImageError(true)}
                loading="lazy"
                referrerPolicy="no-referrer"
            />
        );
    }

    return (
        <div
            className={`${sizeClass} flex flex-shrari-0 items-center justify-center rounded-full bg-ari-100 text-sm font-semibold text-ari-700`}
        >
            {initials}
        </div>
    );
}
