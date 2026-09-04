(function (global) {
    'use strict';

    var SVG_NS = 'http://www.w3.org/2000/svg';

    function svgElement(name, attributes) {
        var element = document.createElementNS(SVG_NS, name);
        Object.keys(attributes || {}).forEach(function (key) {
            element.setAttribute(key, attributes[key]);
        });
        return element;
    }

    function gcd(a, b) {
        while (b !== 0) {
            var remainder = a % b;
            a = b;
            b = remainder;
        }
        return a;
    }

    function DevicePattern(svg, options) {
        if (!svg) {
            throw new Error('Elemento SVG do padrão não encontrado.');
        }

        this.svg = svg;
        this.options = options || {};
        this.grid = Number(this.options.grid) || 3;
        this.readOnly = Boolean(this.options.readOnly);
        this.disabled = false;
        this.sequence = [];
        this.nodes = [];
        this.drawing = false;
        this.playToken = 0;

        this.onPointerDown = this.handlePointerDown.bind(this);
        this.onPointerMove = this.handlePointerMove.bind(this);
        this.onPointerUp = this.handlePointerUp.bind(this);

        this.svg.setAttribute('viewBox', '0 0 100 100');
        this.svg.classList.add('device-pattern');
        this.svg.style.touchAction = 'none';
        this.render();

        this.svg.addEventListener('pointerdown', this.onPointerDown);
        this.svg.addEventListener('pointermove', this.onPointerMove);
        this.svg.addEventListener('pointerup', this.onPointerUp);
        this.svg.addEventListener('pointercancel', this.onPointerUp);
    }

    DevicePattern.prototype.render = function () {
        while (this.svg.firstChild) {
            this.svg.removeChild(this.svg.firstChild);
        }

        this.line = svgElement('polyline', {'class': 'device-pattern__line', points: ''});
        this.preview = svgElement('line', {'class': 'device-pattern__preview', visibility: 'hidden'});
        this.nodeLayer = svgElement('g', {});
        this.svg.appendChild(this.line);
        this.svg.appendChild(this.preview);
        this.svg.appendChild(this.nodeLayer);

        this.nodes = [];
        var gap = 80 / Math.max(1, this.grid - 1);
        var radius = Math.max(3.2, 5.2 - ((this.grid - 3) * 0.45));

        for (var row = 0; row < this.grid; row += 1) {
            for (var column = 0; column < this.grid; column += 1) {
                var index = (row * this.grid) + column + 1;
                var x = 10 + (column * gap);
                var y = 10 + (row * gap);
                var group = svgElement('g', {
                    'class': 'device-pattern__node',
                    'data-index': String(index),
                    'aria-label': 'Ponto ' + index
                });
                group.appendChild(svgElement('circle', {
                    'class': 'device-pattern__dot', cx: x, cy: y, r: radius
                }));
                var label = svgElement('text', {
                    'class': 'device-pattern__label', x: x, y: y
                });
                label.textContent = String(index);
                group.appendChild(label);
                this.nodeLayer.appendChild(group);
                this.nodes.push({index: index, x: x, y: y, element: group});
            }
        }

        this.paint(this.sequence.length);
    };

    DevicePattern.prototype.setGrid = function (grid) {
        grid = Number(grid);
        if (grid < 3 || grid > 6 || Math.floor(grid) !== grid) {
            throw new Error('A grade deve estar entre 3 e 6.');
        }
        this.grid = grid;
        this.sequence = [];
        this.render();
        this.emitChange();
    };

    DevicePattern.prototype.setDisabled = function (disabled) {
        this.disabled = Boolean(disabled);
        this.svg.setAttribute('aria-disabled', this.disabled ? 'true' : 'false');
        this.svg.style.opacity = this.disabled ? '0.55' : '1';
    };

    DevicePattern.prototype.clear = function () {
        this.playToken += 1;
        this.sequence = [];
        this.paint(0);
        this.preview.setAttribute('visibility', 'hidden');
        this.emitChange();
    };

    DevicePattern.prototype.setValue = function (sequence) {
        this.sequence = [];
        var self = this;
        (Array.isArray(sequence) ? sequence : []).forEach(function (point) {
            self.addPoint(Number(point));
        });
        this.paint(this.sequence.length);
        this.emitChange();
    };

    DevicePattern.prototype.getValue = function () {
        return this.sequence.slice();
    };

    DevicePattern.prototype.play = function (options) {
        var self = this;
        var stepMs = Number((options || {}).stepMs) || 280;
        var token = ++this.playToken;
        var count = 0;
        this.paint(0);

        return new Promise(function (resolve) {
            function next() {
                if (token !== self.playToken) {
                    resolve(false);
                    return;
                }
                count += 1;
                self.paint(count);
                if (count < self.sequence.length) {
                    global.setTimeout(next, stepMs);
                } else {
                    resolve(true);
                }
            }

            if (self.sequence.length === 0) {
                resolve(true);
                return;
            }
            global.setTimeout(next, Math.min(stepMs, 120));
        });
    };

    DevicePattern.prototype.destroy = function () {
        this.playToken += 1;
        this.svg.removeEventListener('pointerdown', this.onPointerDown);
        this.svg.removeEventListener('pointermove', this.onPointerMove);
        this.svg.removeEventListener('pointerup', this.onPointerUp);
        this.svg.removeEventListener('pointercancel', this.onPointerUp);
    };

    DevicePattern.prototype.handlePointerDown = function (event) {
        if (this.readOnly || this.disabled || (event.button !== undefined && event.button !== 0)) {
            return;
        }
        event.preventDefault();
        this.clear();
        this.drawing = true;
        if (this.svg.setPointerCapture) {
            this.svg.setPointerCapture(event.pointerId);
        }
        this.addNearestPoint(this.eventPosition(event));
    };

    DevicePattern.prototype.handlePointerMove = function (event) {
        if (!this.drawing || this.readOnly || this.disabled) {
            return;
        }
        event.preventDefault();
        var position = this.eventPosition(event);
        this.addNearestPoint(position);

        var last = this.nodeByIndex(this.sequence[this.sequence.length - 1]);
        if (last) {
            this.preview.setAttribute('x1', last.x);
            this.preview.setAttribute('y1', last.y);
            this.preview.setAttribute('x2', position.x);
            this.preview.setAttribute('y2', position.y);
            this.preview.setAttribute('visibility', 'visible');
        }
    };

    DevicePattern.prototype.handlePointerUp = function (event) {
        if (!this.drawing) {
            return;
        }
        event.preventDefault();
        this.drawing = false;
        this.preview.setAttribute('visibility', 'hidden');
        if (this.svg.releasePointerCapture && this.svg.hasPointerCapture(event.pointerId)) {
            this.svg.releasePointerCapture(event.pointerId);
        }
        this.emitChange();
    };

    DevicePattern.prototype.eventPosition = function (event) {
        var point = this.svg.createSVGPoint();
        point.x = event.clientX;
        point.y = event.clientY;
        var matrix = this.svg.getScreenCTM();
        if (matrix) {
            point = point.matrixTransform(matrix.inverse());
        }
        return {x: point.x, y: point.y};
    };

    DevicePattern.prototype.addNearestPoint = function (position) {
        var threshold = Math.max(5, 8 - ((this.grid - 3) * 0.7));
        var nearest = null;
        var distance = Infinity;
        this.nodes.forEach(function (node) {
            var current = Math.hypot(position.x - node.x, position.y - node.y);
            if (current <= threshold && current < distance) {
                nearest = node;
                distance = current;
            }
        });
        if (nearest) {
            this.addPoint(nearest.index);
        }
    };

    DevicePattern.prototype.addPoint = function (point) {
        if (!this.nodeByIndex(point) || this.sequence.indexOf(point) !== -1) {
            return;
        }

        var previous = this.sequence.length ? this.sequence[this.sequence.length - 1] : null;
        if (previous !== null) {
            var from = this.coordinates(previous);
            var to = this.coordinates(point);
            var rowDelta = to.row - from.row;
            var columnDelta = to.column - from.column;
            var steps = gcd(Math.abs(rowDelta), Math.abs(columnDelta));

            for (var step = 1; step < steps; step += 1) {
                var row = from.row + ((rowDelta / steps) * step);
                var column = from.column + ((columnDelta / steps) * step);
                var intermediate = (row * this.grid) + column + 1;
                if (this.sequence.indexOf(intermediate) === -1) {
                    this.sequence.push(intermediate);
                }
            }
        }

        this.sequence.push(point);
        this.paint(this.sequence.length);
        this.emitChange();
    };

    DevicePattern.prototype.paint = function (count) {
        var visible = this.sequence.slice(0, count);
        var self = this;
        this.nodes.forEach(function (node) {
            node.element.classList.toggle('is-selected', visible.indexOf(node.index) !== -1);
        });
        this.line.setAttribute('points', visible.map(function (point) {
            var node = self.nodeByIndex(point);
            return node.x + ',' + node.y;
        }).join(' '));
    };

    DevicePattern.prototype.coordinates = function (point) {
        return {
            row: Math.floor((point - 1) / this.grid),
            column: (point - 1) % this.grid
        };
    };

    DevicePattern.prototype.nodeByIndex = function (point) {
        for (var index = 0; index < this.nodes.length; index += 1) {
            if (this.nodes[index].index === Number(point)) {
                return this.nodes[index];
            }
        }
        return null;
    };

    DevicePattern.prototype.emitChange = function () {
        if (typeof this.options.onChange === 'function') {
            this.options.onChange(this.getValue());
        }
    };

    global.DevicePattern = DevicePattern;
}(window));
