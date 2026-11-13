-- Crea las tablas y sus restricciones en una base vacía; los roles se insertan al final.
SET NAMES utf8mb4;
SET time_zone = '-06:00';

-- Define los permisos disponibles: cliente y administrador.
CREATE TABLE rol (
  id_rol BIGINT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(30) NOT NULL,
  PRIMARY KEY (id_rol),
  UNIQUE KEY uk_rol_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guarda la cuenta y el hash de su contraseña; ruta_imagen contiene la URL de Firebase.
CREATE TABLE usuario (
  id_usuario BIGINT NOT NULL AUTO_INCREMENT,
  password VARCHAR(512) NOT NULL,
  nombre VARCHAR(50) NOT NULL,
  apellidos VARCHAR(75) NOT NULL,
  email VARCHAR(75) NOT NULL,
  telefono VARCHAR(25) NULL,
  direccion VARCHAR(255) NULL,
  ruta_imagen VARCHAR(1024) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uk_usuario_email (email),
  INDEX idx_usuario_activo (activo),
  INDEX idx_usuario_fecha_creacion (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relaciona las cuentas con sus permisos mediante una clave compuesta sin duplicados.
CREATE TABLE usuario_rol (
  id_usuario BIGINT NOT NULL,
  id_rol BIGINT NOT NULL,
  PRIMARY KEY (id_usuario, id_rol),
  INDEX idx_usuario_rol_rol (id_rol),
  CONSTRAINT fk_usuario_rol_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_usuario_rol_rol
    FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clasifica los libros y evita categorías repetidas por descripción.
CREATE TABLE categoria (
  id_categoria BIGINT NOT NULL AUTO_INCREMENT,
  descripcion VARCHAR(120) NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uk_categoria_descripcion (descripcion),
  INDEX idx_categoria_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva los autores que se relacionan con cada ficha de libro.
CREATE TABLE autor (
  id_autor BIGINT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id_autor),
  UNIQUE KEY uk_autor_nombre (nombre),
  INDEX idx_autor_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva las editoriales utilizadas por el catálogo.
CREATE TABLE editorial (
  id_editorial BIGINT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(120) NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (id_editorial),
  UNIQUE KEY uk_editorial_nombre (nombre),
  INDEX idx_editorial_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reúne la ficha bibliográfica, el precio y las existencias; las referencias apuntan a sus catálogos.
CREATE TABLE libro (
  id_libro BIGINT NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(150) NOT NULL,
  descripcion VARCHAR(1000) NOT NULL,
  precio DECIMAL(12,2) NOT NULL,
  existencias INT NOT NULL DEFAULT 0,
  isbn VARCHAR(20) NOT NULL,
  ubicacion_fisica VARCHAR(80) NOT NULL,
  ruta_imagen VARCHAR(1000) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  id_categoria BIGINT NOT NULL,
  id_autor BIGINT NOT NULL,
  id_editorial BIGINT NOT NULL,
  PRIMARY KEY (id_libro),
  UNIQUE KEY uk_libro_isbn (isbn),
  INDEX idx_libro_titulo (titulo),
  INDEX idx_libro_activo_existencias (activo, existencias),
  INDEX idx_libro_categoria (id_categoria),
  INDEX idx_libro_autor (id_autor),
  INDEX idx_libro_editorial (id_editorial),
  CONSTRAINT chk_libro_precio CHECK (precio > 0),
  CONSTRAINT chk_libro_existencias CHECK (existencias >= 0),
  CONSTRAINT fk_libro_categoria
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria) ON DELETE RESTRICT,
  CONSTRAINT fk_libro_autor
    FOREIGN KEY (id_autor) REFERENCES autor(id_autor) ON DELETE RESTRICT,
  CONSTRAINT fk_libro_editorial
    FOREIGN KEY (id_editorial) REFERENCES editorial(id_editorial) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asocia un descuento y un periodo de vigencia a un libro.
CREATE TABLE oferta (
  id_oferta BIGINT NOT NULL AUTO_INCREMENT,
  id_libro BIGINT NOT NULL,
  descripcion VARCHAR(150) NOT NULL,
  porcentaje_descuento DECIMAL(5,2) NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_oferta),
  INDEX idx_oferta_libro (id_libro),
  INDEX idx_oferta_vigencia (activo, fecha_inicio, fecha_fin),
  CONSTRAINT chk_oferta_descuento
    CHECK (porcentaje_descuento > 0 AND porcentaje_descuento < 100),
  CONSTRAINT chk_oferta_fechas CHECK (fecha_fin >= fecha_inicio),
  CONSTRAINT fk_oferta_libro
    FOREIGN KEY (id_libro) REFERENCES libro(id_libro) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guarda la preferencia de un cliente por un libro y evita repetir la misma pareja.
CREATE TABLE favorito (
  id_favorito BIGINT NOT NULL AUTO_INCREMENT,
  id_usuario BIGINT NOT NULL,
  id_libro BIGINT NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id_favorito),
  UNIQUE KEY uk_favorito_usuario_libro (id_usuario, id_libro),
  INDEX idx_favorito_usuario_fecha (id_usuario, fecha_creacion),
  INDEX idx_favorito_libro (id_libro),
  CONSTRAINT fk_favorito_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE,
  CONSTRAINT fk_favorito_libro
    FOREIGN KEY (id_libro) REFERENCES libro(id_libro) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agrupa la compra en preparación; se desactiva cuando se convierte en pedido.
CREATE TABLE carrito (
  id_carrito BIGINT NOT NULL AUTO_INCREMENT,
  id_usuario BIGINT NOT NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_carrito),
  INDEX idx_carrito_usuario_activo (id_usuario, activo),
  CONSTRAINT fk_carrito_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guarda los libros y cantidades del carrito; sus precios se recalculan antes de comprar.
CREATE TABLE carrito_detalle (
  id_carrito_detalle BIGINT NOT NULL AUTO_INCREMENT,
  id_carrito BIGINT NOT NULL,
  id_libro BIGINT NOT NULL,
  cantidad INT NOT NULL,
  precio_unitario DECIMAL(12,2) NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_carrito_detalle),
  UNIQUE KEY uk_carrito_detalle_libro (id_carrito, id_libro),
  INDEX idx_carrito_detalle_libro (id_libro),
  CONSTRAINT chk_carrito_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_carrito_detalle_precio CHECK (precio_unitario >= 0),
  CONSTRAINT fk_carrito_detalle_carrito
    FOREIGN KEY (id_carrito) REFERENCES carrito(id_carrito) ON DELETE CASCADE,
  CONSTRAINT fk_carrito_detalle_libro
    FOREIGN KEY (id_libro) REFERENCES libro(id_libro) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva los totales, estado, método de pago y datos de entrega de la compra confirmada.
CREATE TABLE pedido (
  id_pedido BIGINT NOT NULL AUTO_INCREMENT,
  id_usuario BIGINT NOT NULL,
  fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  subtotal DECIMAL(12,2) NOT NULL,
  impuesto DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'Pendiente',
  metodo_pago VARCHAR(20) NOT NULL DEFAULT 'Tarjeta',
  direccion_envio VARCHAR(255) NULL,
  telefono_envio VARCHAR(25) NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pedido),
  INDEX idx_pedido_usuario_fecha (id_usuario, fecha),
  INDEX idx_pedido_estado (estado),
  INDEX idx_pedido_fecha_modificacion (fecha_modificacion),
  CONSTRAINT chk_pedido_subtotal CHECK (subtotal >= 0),
  CONSTRAINT chk_pedido_impuesto CHECK (impuesto >= 0),
  CONSTRAINT chk_pedido_total CHECK (total > 0),
  CONSTRAINT chk_pedido_estado
    CHECK (estado IN ('Pendiente', 'Pagado', 'Enviado', 'Entregado', 'Cancelado')),
  CONSTRAINT chk_pedido_metodo_pago
    CHECK (metodo_pago IN ('Tarjeta', 'Sinpe', 'Transferencia', 'Efectivo')),
  CONSTRAINT fk_pedido_usuario
    FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conserva el precio y descuento aplicados a cada línea para no alterar el historial al editar el catálogo.
CREATE TABLE pedido_detalle (
  id_pedido_detalle BIGINT NOT NULL AUTO_INCREMENT,
  id_pedido BIGINT NOT NULL,
  id_libro BIGINT NOT NULL,
  precio_historico DECIMAL(12,2) NOT NULL,
  descuento_porcentaje DECIMAL(5,2) NULL,
  cantidad INT NOT NULL,
  subtotal DECIMAL(12,2) NOT NULL,
  fecha_creacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id_pedido_detalle),
  INDEX idx_pedido_detalle_pedido (id_pedido),
  INDEX idx_pedido_detalle_libro (id_libro),
  CONSTRAINT chk_pedido_detalle_precio CHECK (precio_historico >= 0),
  CONSTRAINT chk_pedido_detalle_descuento CHECK (descuento_porcentaje >= 0 AND descuento_porcentaje < 100),
  CONSTRAINT chk_pedido_detalle_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_pedido_detalle_subtotal CHECK (subtotal >= 0),
  CONSTRAINT fk_pedido_detalle_pedido
    FOREIGN KEY (id_pedido) REFERENCES pedido(id_pedido) ON DELETE CASCADE,
  CONSTRAINT fk_pedido_detalle_libro
    FOREIGN KEY (id_libro) REFERENCES libro(id_libro) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO rol (id_rol, nombre) VALUES (1, 'ROLE_ADMIN'), (2, 'ROLE_CLIENTE');
