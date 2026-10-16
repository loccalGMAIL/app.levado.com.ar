<?php

namespace App\Enums;

enum Jurisdiction: string
{
    case Caba = 'caba';
    case BuenosAires = 'buenos_aires';
    case Catamarca = 'catamarca';
    case Chaco = 'chaco';
    case Chubut = 'chubut';
    case Cordoba = 'cordoba';
    case Corrientes = 'corrientes';
    case EntreRios = 'entre_rios';
    case Formosa = 'formosa';
    case Jujuy = 'jujuy';
    case LaPampa = 'la_pampa';
    case LaRioja = 'la_rioja';
    case Mendoza = 'mendoza';
    case Misiones = 'misiones';
    case Neuquen = 'neuquen';
    case RioNegro = 'rio_negro';
    case Salta = 'salta';
    case SanJuan = 'san_juan';
    case SanLuis = 'san_luis';
    case SantaCruz = 'santa_cruz';
    case SantaFe = 'santa_fe';
    case SantiagoDelEstero = 'santiago_del_estero';
    case TierraDelFuego = 'tierra_del_fuego';
    case Tucuman = 'tucuman';

    public function label(): string
    {
        return match ($this) {
            self::Caba => 'Ciudad Autónoma de Buenos Aires',
            self::BuenosAires => 'Buenos Aires',
            self::Catamarca => 'Catamarca',
            self::Chaco => 'Chaco',
            self::Chubut => 'Chubut',
            self::Cordoba => 'Córdoba',
            self::Corrientes => 'Corrientes',
            self::EntreRios => 'Entre Ríos',
            self::Formosa => 'Formosa',
            self::Jujuy => 'Jujuy',
            self::LaPampa => 'La Pampa',
            self::LaRioja => 'La Rioja',
            self::Mendoza => 'Mendoza',
            self::Misiones => 'Misiones',
            self::Neuquen => 'Neuquén',
            self::RioNegro => 'Río Negro',
            self::Salta => 'Salta',
            self::SanJuan => 'San Juan',
            self::SanLuis => 'San Luis',
            self::SantaCruz => 'Santa Cruz',
            self::SantaFe => 'Santa Fe',
            self::SantiagoDelEstero => 'Santiago del Estero',
            self::TierraDelFuego => 'Tierra del Fuego',
            self::Tucuman => 'Tucumán',
        };
    }
}
