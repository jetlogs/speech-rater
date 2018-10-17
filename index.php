<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <title>Speech Rater Prototype</title>

  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.3/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.4.1/css/all.css">
  <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
  <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
  <script src="https://unpkg.com/axios/dist/axios.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vue/dist/vue.js"></script>


  <style type="text/css">
    [v-cloak] {
      display: none
    }

    body {
      padding-top: 3rem;
    }

    .ql-container {
      font-size: 1rem;
      margin-top: 10px;
      margin-bottom: 10px;
      height: 300px;
      border: none !important;
    }

    .ql-editor {
      padding: 0;
    }

    #audio-player {
      width: 100%;
    }

    .col-sm {
      border: 1px solid grey;
      padding: 10px;
      margin: 10px;
    }

    .wordItem {
      display: inline-block;
    }

    .wordItemSpace {
      margin-left: 3px;
    }

    .wordItemPlaying {
      background-color: rgba(0, 0, 255, 0.3);
    }
  </style>

</head>

<body>

  <div id="app" class="container" v-cloak>

    <ul class="nav nav-pills">
      <li class="nav-item" v-for='(obj,key) in stages'>
        <a @click='changeStage(obj,key)' :class="['nav-link',obj.disabled?'disabled':'',stage==key?'active':'']" href="javascript:void(0)">{{obj.label}}</a>
      </li>
    </ul>

    <div class="row">

      <div style='background-color:lightgrey;cursor:not-allowed;' class="col-sm" v-if="stage == 'transcribe'">
        <h4>Computer Transcript</h4>
        <div :class="wordItemClass(item)" v-for="item in items">{{item.alternatives[0].content}}</div>
        <hr/>
        <audio @timeupdate="audioTime = $event.target.currentTime" id="audio-player" controls src="eiken-grade2-q2-sample-answer.mp3"></audio>
      </div>

      <div class="col-sm">
        <h4>Human Transcript</h4>
        <div id="editor"></div>
      </div>

      <div class="col-sm" v-if="stage == 'correct' || stage == 'keywords'">

        <list-items v-if="selectedItem===null" :items="currentItems()" @item-selected='selectItem'></list-items>
        <selected-item v-if="selectedItem!==null" :selected_item="selectedItem" @deselect-item="deselectItem" @delete-item="deleteItem"></selected-item>

      </div>

      <div v-if="stage=='criteria'" class="col-sm">

        <div class="form-check" v-for="(criterion,idx) in criteria">
          <input v-model="criterion.checked" class="form-check-input" type="checkbox" :id="'criterion_'+idx">
          <label class="form-check-label" :for="'criterion_'+idx">
            {{criterion.content}}
          </label>
        </div>

      </div>

      <div v-if='stage=="results"' class="col-sm">
        <div>
          
           <h2>Mistakes</h2>
          <ul>
            <li v-for='mistake in mistakes'>
              {{mistake.content}}:{{mistake.type}}
            </li>
          </ul>

          <h2>Keywords</h2>
          <ul>
            <li v-for='keyword in keywords'>
              {{keyword.content}}:{{keyword.type}}
            </li>
          </ul>

          <h2>Criteria</h2>
          <ul>
            <li v-for='criterion in criteria'>
              {{criterion.content}}:{{criterion.checked}}
            </li>
          </ul>

        </div>
      </div>

    </div>

    <button v-if='stage=="transcribe"' style='margin-bottom:10px;' class="btn btn-lg btn-success btn-block" @click='transcriptComplete()'>Done</button>
    <div class='alert alert-info' style='font-size:20px;'><i class="fas fa-info-circle"></i> {{stages[stage].instructions}}</div>

  </div>

  <script>
    Vue.component('selected-item', {
      props: ['selected_item'],
      template: `

         <div class="card">
          <div class="card-body">
            <h5 class="card-title">Selected Item</h5>
            <p>{{selected_item.content}}</p>
            <div class="form-group" v-if="selected_item.type!=='keyword'">
              <label for="itemType">Item Type</label>
              <select id="itemType" class="form-control" v-model="selected_item.type">
                <option v-for="type in itemTypes" :value="type">{{type}}</option>
              </select>
            </div>
            <a @click="deselectItem()" href="javascript:void(0)" class="card-link float-right">OK</a>
            <a @click="deleteItem(selected_item.id)" href="javascript:void(0)" class="card-link float-left">Delete</a>
          </div>
        </div>

      `,
      data: function() {
        return {
          itemTypes: ["none", "preposition", "article", "verb", "noun", "adverb", "adjective"],
        }
      },
      methods: {
        deleteItem: function(id) {
          this.$emit('delete-item', id);
        },
        deselectItem: function() {
          this.$emit('deselect-item');
        }
      }
    });

    Vue.component('list-items', {
      props: ['items'],
      template: `

      <div class="list-group" style="height:300px;overflow:auto;">
      <a @click="selectItem(item)" v-for="item in items" href="javascript:void(0)" class="list-group-item list-group-item-action">
      {{item.content.length>20?item.content.slice(0,20)+"...":item.content}} <span :class="['badge',item.type==='none'?'badge-danger':'badge-primary','float-right']">{{item.type}}</span>
      </a>
      </div>

      `,
      methods: {
        selectItem: function(item) {
          this.$emit('item-selected', item);
        }
      }
    });

    var app = new Vue({
      el: "#app",
      data: {
        audioTime: 0,
        transcript: "",
        items: [],
        selectedItem: null,
        stage: 'transcribe',
        stages: {
          "transcribe": {
            label: "Transcribe",
            instructions: "Correct the transcription",
            disabled: false
          },
          "correct": {
            label: "Find Mistakes",
            instructions: "Highlight grammar and vocabulary mistakes",
            disabled: true
          },
          "keywords": {
            label: "Find Keywords",
            instructions: "Highlight key content words and phrases",
            disabled: true
          },
          "criteria": {
            label: "Check Criteria",
            instructions: "Select the applicable criteria",
            disabled: true
          },
          "results": {
            label: "View Results",
            instructions: "View the rating results",
            disabled: true
          }
        },
        criteria: [{
            content: "The examinee described 5 distinct points",
            checked: false
          },
          {
            content: "The examinee skipped one or more frames",
            checked: false
          },
          {
            content: "The examinee quoted the speech bubbles",
            checked: false
          }
        ],
        mistakes: [],
        keywords: [],
        preKeywords: ["art museum", "childcare services", "Sasaki", "ten minutes later", "baby", "shopping center", "take care of", "look around", "gift shop", "crying", "toy", "locker", "bag", "paintings", "artwork", "worried", "looking forward to"],
        corrected: ""
      },
      methods: {
        changeStage: function(obj, key) {
          if (!obj.disabled) {
            this.stage = key;
          }
        },
        transcriptComplete: function() {
          
          app.stage = 'correct';
          app.stages['transcribe'].disabled = true;
          
          ['correct', 'keywords', 'criteria', 'results'].forEach(function(e) {
            app.stages[e].disabled = false;
          });
          
          this.corrected = this.quill.getText();
          this.getKeywords();
          this.quill.disable();
          
        },
        wordItemClass: function(item) {
          return {
            "wordItem": true,
            "wordItemSpace": /^[a-zA-Z0-9']+$/.test(item.alternatives[0].content),
            "wordItemPlaying": item.start_time <= this.audioTime && item.end_time >= this.audioTime
          }
        },
        getKeywords: function() {
          var vm = this;
          var re;
          this.preKeywords.forEach(function(keyword, idx) {
            re = new RegExp("\\b" + keyword + "\\b", "g");
            vm.corrected.replace(re, function(match, index) {
              vm.keywords.push({
                "id": vm.uniqueID(),
                "content": keyword,
                "type": 'keyword',
                "start": index,
                "length": match.length
              })
            });
          });
        },
        currentItems: function() {
          if (this.stage == 'correct') {
            return this.mistakes;
          } else if (this.stage == 'keywords') {
            return this.keywords;
          }
        },
        deselectItem: function() {
          this.selectedItem = null;
        },
        selectItem: function(item) {
          this.selectedItem = item;
        },
        deleteItem: function(id) {
          var index = this.currentItems().map(function(e) {
            return e.id
          }).indexOf(id);
          if (index !== -1) {
            this.currentItems().splice(index, 1);
          }
          this.selectedItem = null;
          this.updateHighlights();
        },
        uniqueID: function() {
          return '_' + Math.random().toString(36).substr(2, 9);
        },
        updateHighlights: function() {
          this.quill.removeFormat(0, this.corrected.length);
          var vm = this;
          var which, color;
          if (this.stage == 'correct') {
            which = this.mistakes;
            color = 'rgba(255,0,0,0.3)';
          } else if (this.stage == 'keywords') {
            which = this.keywords;
            color = 'rgba(0,0,255,0.3)';
          }
          which.forEach(function(obj, idx) {
            vm.quill.formatText(obj.start, obj.length, 'background', color);
          });
        },
        gotSelection: function(range, oldRange, source) {
          if (range) {
            if (range.length == 0) {
              console.log('User cursor is on', range.index);
            } else {
              var text = this.quill.getText(range.index, range.length);
              if (this.stage == 'correct') {
                this.mistakes.unshift({
                  id: this.uniqueID(),
                  content: text,
                  type: "none",
                  start: range.index,
                  length: range.length
                });
                this.quill.setSelection(null);
                this.updateHighlights();
              }
              if (this.stage == 'keywords') {
                this.keywords.unshift({
                  id: this.uniqueID(),
                  content: text,
                  type: "keyword",
                  start: range.index,
                  length: range.length
                });
                this.quill.setSelection(null);
                this.updateHighlights();
              }
            }
          } else {
            console.log('Cursor not in the editor');
          }
        }
      },
      watch: {
        'stage': function() {
          this.quill.removeFormat(0, this.corrected.length);
          if (this.stage == 'correct' || this.stage == 'keywords') {
            this.updateHighlights();
          }
        }
      },
      mounted: function() {

        var vm = this;

        this.quill = new Quill('#editor', {
          theme: 'snow',
          "modules": {
            "toolbar": false
          }
        });

        this.quill.focus();

        axios.get('asrOutput.json')
          .then(function(response) {

            vm.transcript = response.data.results.transcripts[0].transcript;
            vm.items = response.data.results.items;
            vm.quill.setText(vm.transcript + "\n");

            vm.quill.on('selection-change', function(range, oldRange, source) {
              vm.gotSelection(range, oldRange, source);
            });

            vm.quill.on('text-change', function(contents, oldContents, source) {
              if (source == "user") {



              }
            });

          })
          .catch(function(error) {
            console.log(error);
          })
          .then(function() {
            // always executed
          });

      }

    });
  </script>

</body>

</html>