<?php

namespace App\Services\Demo;

/**
 * Datos inventados de la demo pública (demo.onfactu.com).
 *
 * La empresa de ejemplo es un autónomo de diseño y comunicación. Así la demo
 * enseña la retención de IRPF, que es de lo más buscado, y queda fiscalmente
 * correcta: retención solo a empresas, IVA intracomunitario a clientes de la
 * UE, y sin retención a particulares.
 *
 * Todos los correos van a @demo.onfactu.com, que es nuestro: si algún día se
 * activa el envío, nada saldrá hacia dominios de terceros.
 */
class CatalogoDemo
{
    public const DOMINIO_CORREO = 'demo.onfactu.com';

    public static function empresa(): array
    {
        return [
            'nombre'    => 'Estudio Nexo',
            'titular'   => 'Laura Méndez Ortiz',
            'nif'       => self::dni(47521836),
            'email'     => 'hola@' . self::DOMINIO_CORREO,
            'web'       => 'demo.onfactu.com',
            'telefono'  => '910 000 000',
            'direccion' => 'Calle del Prado, 14, 2.º B',
            'ciudad'    => 'Madrid',
            'provincia' => 'Madrid',
            'cp'        => '28014',
        ];
    }

    /**
     * tipo: empresa (con retención), particular (sin retención) o ue (IVA intracomunitario).
     */
    public static function clientes(): array
    {
        $e = fn ($n, $cif, $mail, $contacto, $dir, $ciudad, $prov, $cp) => [
            'tipo' => 'empresa', 'nombre' => $n, 'nif' => $cif, 'email' => $mail . '@' . self::DOMINIO_CORREO,
            'contacto' => $contacto, 'direccion' => $dir, 'ciudad' => $ciudad, 'provincia' => $prov,
            'cp' => $cp, 'pais' => 'ES',
        ];
        $p = fn ($n, $dni, $mail, $dir, $ciudad, $prov, $cp) => [
            'tipo' => 'particular', 'nombre' => $n, 'nif' => $dni, 'email' => $mail . '@' . self::DOMINIO_CORREO,
            'contacto' => null, 'direccion' => $dir, 'ciudad' => $ciudad, 'provincia' => $prov,
            'cp' => $cp, 'pais' => 'ES',
        ];

        return [
            $e('Construcciones Altamira S.L.', self::cif(3184527), 'administracion.altamira', 'Ramón Pereda', 'Calle Castelar, 22', 'Santander', 'Cantabria', '39004'),
            $e('Clínica Dental Sonrisa Plus S.L.', self::cif(4629135), 'recepcion.sonrisaplus', 'Marta Vilar', 'Avenida del Puerto, 115', 'Valencia', 'Valencia', '46023'),
            $e('Bodegas Viña del Cierzo S.L.', self::cif(5073916), 'pedidos.cierzo', 'Íñigo Lasheras', 'Camino de la Almunia, 8', 'Zaragoza', 'Zaragoza', '50012'),
            $e('Academia de Idiomas Babel S.L.', self::cif(2946183), 'secretaria.babel', 'Nuria Castro', 'Calle Zamora, 41', 'Salamanca', 'Salamanca', '37002'),
            $e('Inmobiliaria Puerta del Sol S.L.', self::cif(6318204), 'oficina.puertadelsol', 'Andrés Galán', 'Calle Mayor, 57', 'Madrid', 'Madrid', '28013'),
            $e('Transportes Rápidos del Norte S.L.', self::cif(1857340), 'trafico.rapidosnorte', 'Koldo Urrutia', 'Polígono Ugaldeguren, parcela 12', 'Bilbao', 'Vizcaya', '48170'),
            $e('Floristería Las Camelias S.L.', self::cif(7205618), 'tienda.lascamelias', 'Covadonga Riera', 'Calle Uría, 30', 'Oviedo', 'Asturias', '33003'),
            $e('Hotel Mirador de la Bahía S.L.', self::cif(3461907), 'reservas.miradorbahia', 'Pilar Aragón', 'Paseo Marítimo, 3', 'Cádiz', 'Cádiz', '11010'),
            $e('Gimnasio Fuerza Vital S.L.', self::cif(8129463), 'info.fuerzavital', 'Sergio Molina', 'Avenida de la Libertad, 90', 'Murcia', 'Murcia', '30009'),
            $e('Horizonte Asesores S.L.', self::cif(4870251), 'contacto.horizonte', 'Beatriz Sanz', 'Calle Santiago, 18', 'Valladolid', 'Valladolid', '47001'),
            $e('Panadería Horno de Leña S.L.', self::cif(6593728), 'obrador.hornodelena', 'Rafael Ortiz', 'Calle Cruz Conde, 6', 'Córdoba', 'Córdoba', '14001'),
            $p('Lucía Fernández Gómez', self::dni(28451937), 'lucia.fernandez', 'Calle Larios, 9, 3.º A', 'Málaga', 'Málaga', '29005'),
            $p('Javier Moreno Castillo', self::dni(75329164), 'javier.moreno', 'Calle Recogidas, 27', 'Granada', 'Granada', '18005'),
            $p('Carmen Ortega Ruiz', self::dni(21784530), 'carmen.ortega', 'Avenida Maisonnave, 33', 'Alicante', 'Alicante', '03003'),
            $p('David Navarro Iglesias', self::dni(43197625), 'david.navarro', 'Calle Sant Miquel, 12', 'Palma', 'Baleares', '07002'),
            $p('Elena Romero Vidal', self::dni(34562871), 'elena.romero', 'Paseo de Almería, 44', 'Almería', 'Almería', '04001'),
            ['tipo' => 'ue', 'nombre' => 'Lusitânia Têxteis Lda.', 'nif' => 'PT507123456',
             'email' => 'geral.lusitania@' . self::DOMINIO_CORREO, 'contacto' => 'João Ferreira',
             'direccion' => 'Rua de Santa Catarina, 210', 'ciudad' => 'Porto', 'provincia' => 'Porto',
             'cp' => '4000-442', 'pais' => 'PT'],
            ['tipo' => 'ue', 'nombre' => 'Atelier Lumière SARL', 'nif' => 'FR40123456789',
             'email' => 'contact.lumiere@' . self::DOMINIO_CORREO, 'contacto' => 'Claire Dubois',
             'direccion' => '14 Rue de la République', 'ciudad' => 'Lyon', 'provincia' => 'Rhône',
             'cp' => '69002', 'pais' => 'FR'],
        ];
    }

    /**
     * [nombre, descripción, precio en céntimos, unidad, tipo]. Tipo: servicio o producto.
     * La retención solo se aplica a servicios facturados a empresas.
     */
    public static function articulos(): array
    {
        return [
            ['Diseño de logotipo', 'Tres propuestas y dos rondas de cambios', 45000, 'un', 'servicio'],
            ['Identidad corporativa completa', 'Logotipo, paleta, tipografías y manual de marca', 120000, 'un', 'servicio'],
            ['Diseño web corporativa', 'Hasta 6 secciones, adaptada a móvil', 180000, 'un', 'servicio'],
            ['Tienda online', 'Catálogo, pasarela de pago y formación incluida', 320000, 'un', 'servicio'],
            ['Mantenimiento web mensual', 'Actualizaciones, copias de seguridad y soporte', 9000, 'mes', 'servicio'],
            ['Gestión de redes sociales', 'Planificación y publicación mensual', 35000, 'mes', 'servicio'],
            ['Hora de consultoría', 'Consultoría de marketing digital', 6000, 'h', 'servicio'],
            ['Auditoría SEO', 'Informe técnico y plan de acción', 39000, 'un', 'servicio'],
            ['Artículo para blog', 'Redacción optimizada de 1.000 palabras', 7500, 'un', 'servicio'],
            ['Sesión de fotografía de producto', 'Media jornada, hasta 30 productos', 28000, 'un', 'servicio'],
            ['Retoque fotográfico', 'Recorte, color y fondo neutro', 800, 'un', 'servicio'],
            ['Vídeo corporativo', 'Guion, grabación y montaje de 90 segundos', 95000, 'un', 'servicio'],
            ['Gestión de campaña de anuncios', 'Configuración y seguimiento mensual', 25000, 'mes', 'servicio'],
            ['Formación en gestión web', 'Formación a medida', 5000, 'h', 'servicio'],
            ['Alojamiento web anual', 'Hosting con certificado de seguridad', 12000, 'un', 'servicio'],
            ['Renovación de dominio', 'Dominio .es o .com, un año', 1500, 'un', 'servicio'],
            ['Tarjetas de visita (500 uds.)', 'Papel estucado de 350 g, a doble cara', 4500, 'un', 'producto'],
            ['Flyers A5 (1.000 uds.)', 'Papel de 135 g, a doble cara', 12000, 'un', 'producto'],
            ['Roll-up publicitario', 'Estructura de aluminio con lona de 85 x 200 cm', 9500, 'un', 'producto'],
            ['Vinilo para escaparate', 'Impresión y corte a medida, por unidad', 6500, 'un', 'producto'],
        ];
    }

    public static function categoriasGasto(): array
    {
        return [
            'Cuota de autónomos', 'Telefonía e internet', 'Software y suscripciones',
            'Material de oficina', 'Equipos informáticos', 'Publicidad',
            'Formación', 'Asesoría y gestoría', 'Desplazamientos',
        ];
    }

    /** [meses atrás, día, concepto, importe en céntimos, categoría] */
    public static function gastosPuntuales(): array
    {
        return [
            [10, 12, 'Portátil de trabajo', 129900, 'Equipos informáticos'],
            [9, 3, 'Licencia anual de software de diseño', 86856, 'Software y suscripciones'],
            [8, 20, 'Curso de fotografía de producto', 18000, 'Formación'],
            [7, 14, 'Campaña de anuncios en buscadores', 15000, 'Publicidad'],
            [6, 9, 'Material de oficina', 6435, 'Material de oficina'],
            [5, 22, 'Desplazamiento a cliente en tren', 8640, 'Desplazamientos'],
            [4, 16, 'Asesoría fiscal trimestral', 18150, 'Asesoría y gestoría'],
            [3, 7, 'Disco duro externo 2 TB', 8999, 'Equipos informáticos'],
            [2, 25, 'Campaña de anuncios en redes sociales', 12000, 'Publicidad'],
            [1, 16, 'Asesoría fiscal trimestral', 18150, 'Asesoría y gestoría'],
            [0, 2, 'Material de oficina', 3210, 'Material de oficina'],
        ];
    }

    /** [día, concepto, importe en céntimos, categoría] que se repiten cada mes */
    public static function gastosMensuales(): array
    {
        return [
            [5, 'Fibra y móvil', 4840, 'Telefonía e internet'],
            [28, 'Cuota de autónomos', 29400, 'Cuota de autónomos'],
        ];
    }

    /** DNI con su letra de control correcta. */
    public static function dni(int $numero): string
    {
        return sprintf('%08d', $numero) . 'TRWAGMYFPDXBNJZSQVHLCKE'[$numero % 23];
    }

    /** CIF de sociedad limitada (letra B) con su dígito de control correcto. */
    public static function cif(int $numero): string
    {
        $d = sprintf('%07d', $numero);
        $suma = 0;
        for ($i = 0; $i < 7; $i++) {
            $n = (int) $d[$i];
            if ($i % 2 === 0) {
                $n *= 2;
                $n = intdiv($n, 10) + $n % 10;
            }
            $suma += $n;
        }

        return 'B' . $d . ((10 - $suma % 10) % 10);
    }
}
