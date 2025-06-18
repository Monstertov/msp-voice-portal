class RecorderProcessor extends AudioWorkletProcessor {
    constructor() {
        super();
        this._buffer = [];
    }

    process(inputs, outputs) {
        const input = inputs[0];
        if (input.length > 0) {
            // Store the input data
            this._buffer.push(input[0].slice());
        }
        return true;
    }

    // Method to get the recorded data
    getBuffer() {
        return this._buffer;
    }
}

registerProcessor('recorder-worklet', RecorderProcessor); 